<?php

/*
  Based on a submitted URL, take note of the site it mentions.
  Ensures that the site health will be tracked if it wasn't already.
  If $check_health is set to true, this function may trigger some health checks (CURL requests) when needed.
  Do not enable it unless you have enough execution time to do so.
  But when you do, it's better to check for health whenever a site submits something.
  After all, the highest chance for the server to be online is when it submits activity.
*/
if(! function_exists('notice_site')){
function notice_site($url, $check_health=false)
{
  
  global $a;
  
  //Get the repositories to handle our queries.
  $siteHealthRepository = new \Friendica\Directory\Domain\SiteHealth\SiteHealthRepository();
  
  //Parse the domain from the URL.
  $site = parse_site_from_url($url);
  
  //Search for it in the site-health table.
  $entry = $siteHealthRepository->getHealthByBaseUrl($site);
  
  //If it exists, see if we need to update any flags / statuses.
  if($entry){
    
    //If we are allowed to do health checks...
    if($check_health){
      
      //And the site is in bad health currently, do a check now.
      //This is because you have a high certainty the site may perform better now.
      if($entry['health_score'] < -40){
        run_site_probe($entry['id'], $entry);
      }
      
      //Or if the site has not been probed for longer than the minimum delay.
      //This is to make sure not everything is postponed to the batches.
      elseif(strtotime($entry['dt_last_probed']) < time()-$a->config['site-health']['min_probe_delay']){
        run_site_probe($entry['id'], $entry);
      }
      
    }
    
  }
  
  //If it does not exist.
  else{
    
    //Add it and make sure it is ready for probing.
    $entry = $siteHealthRepository->createEntry(array(
      'base_url' => $site,
      "dt_first_noticed" => date('Y-m-d H:i:s')
    ));
    
    //And in case we should probe now, do so.
    if($check_health && $entry){
      run_site_probe($entry['id'], $entry);
    }
    
  }
  
  //Give other scripts the site health.
  return isset($entry) ? $entry : false;
  
}}

//Extracts the site from a given URL.
if(! function_exists('parse_site_from_url')){
function parse_site_from_url($url)
{
  
  //Currently a simple implementation, but may improve over time.
  #TODO: support subdirectories?
  $urlMeta = parse_url($url);
  return $urlMeta['scheme'].'://'.$urlMeta['host'];
  
}}

//Performs a ping to the given site ID
//You may need to notice the site first before you know it's ID.
if(! function_exists('run_site_ping')){
function run_site_probe($id, &$entry_out)
{
  
  global $a;
  
  $siteProbeRepository = new \Friendica\Directory\Domain\SiteHealth\SiteProbeRepository();
  $siteHealthRepository = new \Friendica\Directory\Domain\SiteHealth\SiteHealthRepository();
  
  //Get the site information from the DB, based on the ID.
  $entry = $siteHealthRepository->getHealthById($id);
  
  //Abort the probe if site is not known.
  if(!$entry){
    logger('Unknown site-health ID being probed: '.$id);
    throw new \Exception('Unknown site-health ID being probed: '.$id);
  }
  
  //Shortcut.
  $base_url = $entry['base_url'];
  $probe_location = $base_url.'/friendica/json';
  
  //Prepare the CURL call.
  $handle = curl_init();
  $options = array(
    
    //Timeouts
    CURLOPT_TIMEOUT => max($a->config['site-health']['probe_timeout'], 1), //Minimum of 1 second timeout.
    CURLOPT_CONNECTTIMEOUT => 1,
    
    //Redirecting
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 8,
    
    //SSL
    CURLOPT_SSL_VERIFYPEER => true,
    // CURLOPT_VERBOSE => true,
    // CURLOPT_CERTINFO => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    
    //Basic request
    CURLOPT_USERAGENT => 'friendica-directory-probe-0.1',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_URL => $probe_location
    
  );
  curl_setopt_array($handle, $options);
  
  //Probe the site.
  $probe_start = microtime(true);
  $probe_data = curl_exec($handle);
  $probe_end = microtime(true);
  
  //Check for SSL problems.
  $curl_statuscode = curl_errno($handle);
  $sslcert_issues = in_array($curl_statuscode, array(
    60, //Could not authenticate certificate with known CA's
    83  //Issuer check failed
  ));
  
  //When it's the certificate that doesn't work.
  if($sslcert_issues){
    
    //Probe again, without strict SSL.
    $options[CURLOPT_SSL_VERIFYPEER] = false;
    
    //Replace the handler.
    curl_close($handle);
    $handle = curl_init();
    curl_setopt_array($handle, $options);
    
    //Probe.
    $probe_start = microtime(true);
    $probe_data = curl_exec($handle);
    $probe_end = microtime(true);
    
    //Store new status.
    $curl_statuscode = curl_errno($handle);
    
  }
  
  //Gather more meta.
  $time = round(($probe_end - $probe_start) * 1000);
  $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
  $type = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
  $effective_url = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
  
  //Done with CURL now.
  curl_close($handle);
  
  #TODO: if the site redirects elsewhere, notice this site and record an issue.
  $wrong_base_url = parse_site_from_url($effective_url) !== $entry['base_url'];
  
  try{
    $data = json_decode($probe_data);
  }catch(\Exception $ex){
    $data = false;
  }
  
  $parse_failed = !$data;
  
  $parsedData = array();
  if(!$parse_failed){
    
    $given_base_url_match = $data->url == $base_url;
    
    //Record the probe speed in a probes table.
    $siteProbeRepository->createEntry(array(
      'site_health_id' => $entry['id'],
      'request_time' => $time,
      'dt_performed' => date('Y-m-d H:i:s')
    ));
    
    //Update any health calculations or otherwise processed data.
    $parsedData = array(
      'dt_last_seen' => date('Y-m-d H:i:s'),
      'name' => $data->site_name,
      'version' => $data->version,
      'plugins' => implode("\r\n",$data->plugins),
      'reg_policy' => $data->register_policy,
      'info' => $data->info,
      'admin_name' => $data->admin_name,
      'admin_profile' => $data->admin_profile
    );
    
    //Did we use HTTPS?
    $urlMeta = parse_url($probe_location);
    if($urlMeta['scheme'] == 'https'){
      $parsedData['ssl_state'] = $sslcert_issues ? '0' : '1';
    } else {
      $parsedData['ssl_state'] = null;
    }
    
    //Do we have a no scrape supporting node? :D
    if(isset($data->no_scrape_url)){
      $parsedData['no_scrape_url'] = $data->no_scrape_url;
    }
    
  }
  
  //Get the new health.
  $version = $parse_failed ? '' : $data->version;
  $health = health_score_after_probe($entry['health_score'], !$parse_failed, $time, $version, $sslcert_issues);
  
  //Update the health.
  $finalData = array_merge($parsedData, array(
    'dt_last_probed' => date('Y-m-d H:i:s'),
    'health_score' => $health
  ));
  
  //Return updated entry data.
  $entry_out = $siteHealthRepository->updateEntry($entry['id'], $finalData);
  
}}

//Determines the new health score after a probe has been executed.
if(! function_exists('health_score_after_probe')){
function health_score_after_probe($current, $probe_success, $time=null, $version=null, $ssl_issues=null)
{
  
  //Probe failed, costs you 30 points.
  if(!$probe_success) return max($current-30, -100);
  
  //A good probe gives you 20 points.
  $current += 20;
  
  //Speed scoring.
  if(intval($time) > 0){
    
    //Pentaly / bonus points.
    if      ($time > 800) $current -= 10; //Bad speed.
    elseif  ($time > 400) $current -=  5; //Still not good.
    elseif  ($time > 250) $current +=  0; //This is normal.
    elseif  ($time > 120) $current +=  5; //Good speed.
    else                  $current += 10; //Excellent speed.
    
    //Cap for bad speeds.
    if      ($time > 800) $current = min(40, $current);
    elseif  ($time > 400) $current = min(60, $current);
    
  }
  
  //Version check.
  if(!empty($version)){
    
    $versionParts = explode('.', $version);
    
    //Older than 3.x.x?
    //Your score can not go above 30 health.
    if(intval($versionParts[0]) < 3){
      $current = min($current, 30);
    }
    
    //Older than 3.2.x?
    elseif(intval($versionParts[1] < 2)){
      $current -= 5; //Somewhat outdated.
    }
    
    #TODO: See if this needs to be more dynamic.
    #TODO: See if this is a proper indicator of health.
    
  }
  
  //SSL problems? That's a big deal.
  if($ssl_issues === true){
    $current -= 10;
  }
  
  //Don't go beyond +100 or -100.
  return max(min(100, $current), -100);
  
}}

//Changes a score into a name. Used for classes and such.
if(! function_exists('health_score_to_name')){
function health_score_to_name($score)
{
  
  if      ($score < -50)  return 'very-bad';
  elseif  ($score <   0)  return 'bad';
  elseif  ($score <  30)  return 'neutral';
  elseif  ($score <  50)  return 'ok';
  elseif  ($score <  80)  return 'good';
  else                    return 'perfect';
  
}}
