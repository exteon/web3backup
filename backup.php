#!/usr/bin/php
<?php
/*
 * Title:               WEB3 Backup Tool
 * Author:              Constantin-Emil MARINA
 * Copyright holder:    SC EXTEON SRL
 * Homepage:            http://www.exteon.ro/en/products/programming-tools/web3Backup
 * License:             Creative Commons Attribution-NonCommercial-ShareAlike (CC BY-NC-SA 4.0)
 * License home:        http://creativecommons.org/licenses/by-nc-sa/4.0/
 * 
 * This application is distributed under the terms of the Creative Commons Attribution-NonCommercial-ShareAlike 4.0 
 * license
 * 
 * No warranties, express or implied
 * 
 */
	$time=microtime(true);
	chdir(dirname(__FILE__));
	ini_set('max_execution_time',6*3600);
	
	require_once('backup.config.php');
	
	$targetDir=trim($backupConfig['targetDir']);
	if(!$targetDir){
		echo "!!!! ERROR: No target dir specified\n";
		die(1);
	}
	if(!is_dir($targetDir)){
		echo "!!!! ERROR: Target dir $targetDir does not exist\n";
		die(1);
	}
	
	$tempDir=trim($backupConfig['tempDir']);
	if(!$tempDir){
		echo "!!!! ERROR: No temp dir specified\n";
		die(1);
	}
	if(!prepareFolder($tempDir,true)){
		echo "!!!! ERROR: Could not prepare dir $tempDir\n";
		die(1);
	}
	
	$lockFN=$targetDir.'/backupLock';
	echo "#### Acquiring lock $lockFN\n";
	$lock=fopen($lockFN,'w+');
	if(!$lock){
		echo "!!!! PANIC: Cannot acquire lock\n";
		die(1);
	}
	if(!flock($lock,LOCK_EX)){
		echo "!!!! PANIC: Cannot acquire lock\n";
		die(1);
	}
	
	$stripAll=substr_count($tempDir,'/');
	
	$lastRunDate=null;
	$lastRunFN=$backupConfig['targetDir'].'/backupLastRun';
	if(file_exists($lastRunFN)){
		$f=fopen($lastRunFN,'r');
		if($f){
			$lastRunDate=trim(fgets($f));
			fclose($f);
		}
	}
	
	$DT=new DateTime();
	if($lastRunDate){
		$lastRunDT=new DateTime($lastRunDate);
	} else {
		$lastRunDT=clone $DT;
	}
	$tomorrowDT=clone $DT;
	$tomorrowDT->modify('+1 day');
	$weekday=(int)$DT->format('w');
	$day=(int)$DT->format('j');
	$month=(int)$DT->format('n');
	$year=(int)$DT->format('Y');

	$lastMonth=$month-1;
	if(!$lastMonth){
		$lastMonth=12;
		$lastMonthYear=$year-1;
	} else {
		$lastMonthYear=$year;
	}
	$lastMonthDT=new DateTime("$lastMonthYear-$lastMonth-01");
	$lastMonthDays=(int)$lastMonthDT->format('t');
	if($lastMonthDays<$backupConfig['when']['monthly']['day']){
		$lastMonthlyDay=$lastMonthDays;
	} else {
		$lastMonthlyDay=$backupConfig['when']['monthly']['day'];
	}
	$lastMonthlyDT=new DateTime("$lastMonthYear-$lastMonth-$lastMonthlyDay");
	$lastYear=$year-1;
	$lastYearlyDT=new DateTime("$lastYear-{$backupConfig['when']['yearly']['month']}-{$backupConfig['when']['yearly']['day']}");
	
	$thisMonthDays=(int)$DT->format('t');
	if($thisMonthDays<$backupConfig['when']['monthly']['day']){
		$thisMonthlyDay=$thisMonthDays;
	} else {
		$thisMonthlyDay=$backupConfig['when']['monthly']['day'];
	}
	$thisMonthlyDT=new DateTime("$year-$month-$thisMonthlyDay");
	$thisYearlyDT=new DateTime("$year-{$backupConfig['when']['yearly']['month']}-{$backupConfig['when']['yearly']['day']}");
	
	$lastMonthDay=($tomorrowDay==1);
	$lastYearDay=(
		$month==12 &&
		$tomorrowMonth==1
	);

	$dateFragment=$DT->format('Y-m-d H-i-s');
	
	$do=array();
	if(
		$backupConfig['when']['daily']
	){
		$do[]='daily';
	}
	if(
		$backupConfig['when']['weekly'] &&
		$backupConfig['when']['weekly']['weekday']==$weekday
	){
		$do[]='weekly';
	}
	if(
		$backupConfig['when']['monthly'] &&
		(
			compareDates($DT,$thisMonthlyDT)==0 ||
			compareDates($lastRunDT,$lastMonthlyDT)<0
		)
	){
		$do[]='monthly';
	}
	if(
		$backupConfig['when']['yearly'] &&
		(
			compareDates($DT,$thisYearlyDT)==0 ||
			compareDates($lastRunDT,$lastYearlyDT)<0
		)
	){
		$do[]='yearly';
	}
	
	echo "\n#### Running backup\n";
	echo "Mode    : ".implode(',',$do)."\n";
	
	$incrementals=array();
	$archivables=array();
	foreach($backupConfig['what'] as $key=>$what){
		if(!$what['when']){
			$when=array('daily');
		}
		elseif(is_array($what['when'])){
			$when=$what['when'];
		} else {
			$when=explode(',',$what['when']);
		}
		if(!array_intersect($when,$do)){
			echo "Skipping {$what['type']} item #$key\n";
			continue;
		}
		$mode=trim($what['mode']);
		switch($mode){
			case '':
				$prefix=$tempDir;
				break;
			case 'incremental':
				$prefix=$tempDir.'/incremental';
				if(!prepareFolder($prefix)){
					echo "!!!! ERROR: cannot prepare dir $prefix, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				break;
			default:
				echo "!!!! ERROR: Unknown mode $mode, skipping\n";
				$hasWarning=true;
				continue 2;
		}
		echo "---- Running {$what['type']} item #$key $mode\n";
		switch($what['type']){
			case 'vcs/svn':
				$temp=$prefix.'/vcs';
				$to='vcs';
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				$temp.='/svn';
				$to.='/svn';
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				$temp.='/'.keyFromPath($what['path']);
				$to.='/'.keyFromPath($what['path']);
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				$dumpFile=$temp.'/svn.dump';
				echo "Exporting svn repo {$what['path']} to $dumpFile\n";
				$command="svnadmin dump ".escapeshellarg($what['path'])." 2>&1 > ".escapeshellarg($dumpFile);
				passthru($command,$ret);
				if($ret){
					echo "!!!! VCS export failed\n";
					echo "!!!! $command\n";
					$hasWarning=true;
				}
				if($mode=='incremental'){
					$incrementals[]=array(
						'from'=>$temp,
						'to'=>$to,
						'purgeAfter'=>$what['purgeAfter'],
						'touch'=>true
					);
				} else {
					$archivables[]=array(
						'from'=>$dumpFile,
						'to'=>$to.'/svn.dump',
						'strip'=>$stripAll
					);
				}
				break;
			case 'vcs/hg':
				$temp=$prefix.'/vcs';
				$to='vcs';
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				$temp.='/hg';
				$to.='/hg';
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				$temp.='/'.keyFromPath($what['path']);
				$to.='/'.keyFromPath($what['path']);
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				echo "Exporting hg repo {$what['path']} to $temp\n";
				$command="hg clone --pull -U ".escapeshellarg($what['path'])." ".escapeshellarg($temp)." 2>&1";
				passthru($command,$ret);
				if($ret){
					echo "!!!! VCS export failed\n";
					echo "!!!! $command\n";
					$hasWarning=true;
				}
				if($mode=='incremental'){
					$incrementals[]=array(
						'from'=>$temp,
						'to'=>$to,
						'purgeAfter'=>$what['purgeAfter'],
						'touch'=>true
					);
				} else {
					$archivables[]=array(
						'from'=>$temp,
						'to'=>$to.'/hg.dump',
						'strip'=>$stripAll
					);
				}
				break;
			case 'db/mysql':
				$host=trim($what['host']);
				if(!$host){
					$host='localhost';
				}
				$user=trim($what['user']);
				if(!$user){
					$user='root';
				}
				$temp=$prefix.'/db';
				$to='db';
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				$temp.='/mysql';
				$to.='/mysql';
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				$temp.='/'.keyFromPath($what['host']);
				$to.='/'.keyFromPath($what['host']);
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				echo "Exporting $host databases to $temp\n";
				if(!is_array($what['dbs'])){
					$dbs=explode(',',$what['dbs']);
				} else {
					$dbs=$what['dbs'];
				}
				foreach($dbs as $key=>$db){
				    if(!trim($db)){
					unset($dbs[$key]);
				    }
				}
				if(!$dbs){
				    echo "Exporting ALL dbs\n";
					$command="														\\
						mysqldump													\\
							--add-drop-table										\\
							--add-locks												\\
							--allow-keywords										\\
							--comments												\\
							--compact												\\
							--create-options										\\
							--default-character-set=utf8							\\
							--disable-keys											\\
							--extended-insert										\\
							--hex-blob												\\
							--lock-tables											\\
							--quote-names											\\
							--quick													\\
							--set-charset											\\
							--tz-utc												\\
							--verbose												\\
							--single-transaction 									\\
							--user=".escapeshellarg($user)." 						\\
							--password=".escapeshellarg($what['password'])." 		\\
							--result-file=".escapeshellarg($temp."/all.sql")." 		\\
							--all-databases\\
							2>&1
					";
					passthru($command,$ret);
					if($ret){
						echo "!!!! DB export failed\n";
						echo "!!!! $command\n";
						$hasWarning=true;
					}
					if($mode!='incremental'){
						$archivables[]=array(
							'from'=>$temp."/all.sql",
							'to'=>$to."/all.sql",
							'strip'=>$stripAll
						);
					}
				}
				foreach($dbs as $db){
					echo "+ $db\n";
					$command="														\\
						mysqldump													\\
							--add-drop-table										\\
							--add-locks												\\
							--allow-keywords										\\
							--comments												\\
							--compact												\\
							--create-options										\\
							--default-character-set=utf8							\\
							--disable-keys											\\
							--extended-insert										\\
							--hex-blob												\\
							--lock-tables											\\
							--quote-names											\\
							--quick													\\
							--set-charset											\\
							--tz-utc												\\
							--verbose												\\
							--single-transaction 									\\
							--user=".escapeshellarg($user)." 						\\
							--password=".escapeshellarg($what['password'])." 		\\
							--result-file=".escapeshellarg($temp."/$db.sql")." 		\\
							".escapeshellarg($db)." 								\\
							2>&1
					";
					passthru($command,$ret);
					if($ret){
						echo "!!!! DB export failed\n";
						echo "!!!! $command\n";
						$hasWarning=true;
					}
					if($mode!='incremental'){
						$archivables[]=array(
							'from'=>$temp."/$db.sql",
							'to'=>$to."/$db.sql",
							'strip'=>$stripAll
						);
					}
				}
				if($mode=='incremental'){
					$incrementals[]=array(
						'from'=>$temp,
						'to'=>$to,
						'purgeAfter'=>$what['purgeAfter'],
						'touch'=>true
					);
				}
				break;
			case 'db/sqlite':
				$temp=$prefix.'/db';
				$to='db';
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				$temp.='/sqlite';
				$to.='/sqlite';
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				$temp.='/'.keyFromPath($what['path']);
				$to.='/'.keyFromPath($what['path']);
				if(!prepareFolder($temp)){
					echo "!!!! ERROR: cannot prepare dir $temp, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				$dumpFile=$temp.'/sqlite.dump';
				echo "Exporting sqlite db {$what['path']} to $dumpFile\n";
				$command="sqlite3 ".escapeshellarg($what['path'])." .dump 2>&1 > ".escapeshellarg($dumpFile);
				passthru($command,$ret);
				if($ret){
					echo "!!!! SQLite export failed\n";
					echo "!!!! $command\n";
					$hasWarning=true;
				}
				if($mode=='incremental'){
					$incrementals[]=array(
						'from'=>$temp,
						'to'=>$to,
						'purgeAfter'=>$what['purgeAfter'],
						'touch'=>true
					);
				} else {
					$archivables[]=array(
						'from'=>$dumpFile,
						'to'=>$to.'/sqlite.dump',
						'strip'=>$stripAll
					);
				}
				break;
			case 'file':
				$path=trim($what['path']);
				if(!$path){
					echo "!!!! No path specified, skipping\n";
					$hasWarning=true;
					continue 2;
				}
				if($mode=='incremental'){
					if(!is_dir($path)){
						echo "!!!! ERROR: $path is not a directory\n";
						$hasWarning=true;
						continue 2;
					}
					$incrementals[]=array(
						'from'=>$path,
						'to'=>'files/'.keyFromPath($path),
						'purgeAfter'=>$what['purgeAfter'],
						'exceptions_regexp'=>$what['exceptions_regexp'],
						'exceptions_glob'=>$what['exceptions_glob']
					);
				} else {
					$archivables[]=array(
						'from'=>$path,
						'to'=>'files/'.keyFromPath($path),
						'strip'=>0,
						'exceptions_glob'=>$what['exceptions_glob']
					);
				}
				break;
		}
	}
	
	echo "\n#### Processing archivables\n";
	$dirName=$dateFragment.' ';
	if(in_array('daily',$do)){
		$dirName.='d';
	} else {
		$dirName.='_';
	}
	if(in_array('weekly',$do)){
		$dirName.='w';
	} else {
		$dirName.='_';
	}
	if(in_array('monthly',$do)){
		$dirName.='m';
	} else {
		$dirName.='_';
	}
	if(in_array('yearly',$do)){
		$dirName.='y';
	} else {
		$dirName.='_';
	}
	$dateDirName=$backupConfig['targetDir'].'/'.$dirName;
	if(is_dir($dateDirName)){
		echo "!!!! $dateDirName already exists, cowardly refusing to overwrite\n";
		$hasWarning=true;
	}
	else {
		foreach($archivables as $archivable){
			if(!prepareFolder($dateDirName.'/'.$archivable['to'],false,true)){
				echo "!!!! Cannot create $dateDirName/{$archivable['to']}, skipping\n";
				$hasWarning=true;
				continue;
			}
			if(is_dir($archivable['from'])){
				$destFn=$dateDirName.'/'.$archivable['to'].'.tar.gz';
				echo "---- Creating $destFn\n";
				$command='tar';
				if($archivable['exceptions_glob']){
					foreach($archivable['exceptions_glob'] as $exception){
						$command.=' --exclude='.escapeshellarg($exception);
					}
				}
				if($archivable['strip']){
					$command.=' --strip-components='.$archivable['strip'];
				}
				$command.=' -czf ';
				$command.=escapeshellarg($destFn);
				$command.=' ';
				$command.=escapeshellarg($archivable['from']);
				$command.=' 2>&1';
				echo "$command\n";
				passthru($command,$ret);
				if($ret){
					echo "!!!! tar command failed\n";
					echo "!!!! $command\n";
					$hasWarning=true;
				}
			} else {
				$destFn=$dateDirName.'/'.$archivable['to'].'.gz';
				echo "---- Creating $destFn\n";
				$command='gzip -c ';
				$command.=escapeshellarg($archivable['from']);
				$command.=' > ';
				$command.=escapeshellarg($destFn);
				passthru($command,$ret);
				if($ret){
					echo "!!!! gzip command failed\n";
					echo "!!!! $command\n";
					$hasWarning=true;
				}
			}
		}
	}
	
	echo "\n#### Rotating archivables\n";
	$dh=opendir($targetDir);
	if(!$dh){
		echo "!!!! Cannot open dir {$backupConfig['targetDir']}\n";
		$hasWarning=true;
	} else {
		while($dn=readdir($dh)){
			$fullPath=$targetDir.'/'.$dn;
			if(is_link($fullPath)){
				continue;
			}
			if(!preg_match('`^(\\d{4})-(\\d{2})-(\\d{2}) \\d{2}-\\d{2}-\\d{2} ([d_][w_][m_][y_])$`',$dn,$match)){
				continue;
			}
			$roYear=(int)$match[1];
			$roMonth=(int)$match[2];
			$roDay=(int)$match[3];
			$roDT=new DateTime("$roYear-$roMonth-$roDay");
			$DI=$DT->diff($roDT);
			$doPurge=false;
			$canPurge=true;
			if($match[4][3]=='y'){
				if($backupConfig['when']['yearly']['rotate']){
					if((int)$DI->format('%y')>=$backupConfig['when']['yearly']['rotate']){
						$doPurge=true;
					} else {
						$canPurge=false;
					}
				} else {
					$canPurge=false;
				}
			}
			if($match[4][2]==='m'){
				if($backupConfig['when']['monthly']['rotate']){
					if(12*(int)$DI->format('%y')+(int)$DI->format("%m")>=$backupConfig['when']['monthly']['rotate']){
						$doPurge=true;
					} else {
						$canPurge=false;
					}
				} else {
					$canPurge=false;
				}
			}
			if($match[4][1]==='w'){
				if($backupConfig['when']['weekly']['rotate']){
					if(((int)$DI->format('%a'))/7>=$backupConfig['when']['weekly']['rotate']){
						$doPurge=true;
					} else {
						$canPurge=false;
					}
				} else {
					$canPurge=false;
				}
			}
			if($match[4][0]==='d'){
				if($backupConfig['when']['daily']['rotate']){
					if((int)$DI->format('%a')>=$backupConfig['when']['daily']['rotate']){
						$doPurge=true;
					} else {
						$canPurge=false;
					}
				} else {
					$canPurge=false;
				}
			}
			if(
				$doPurge &&
				$canPurge
			){
				echo "---- Purging $fullPath\n";
				if(!delDir($fullPath)){
					echo "!!!! ERROR: Could not delete $fullPath\n";
					$hasWarning=true;
				}
			}
		}
		closedir($dh);
	}
	
	echo "\n#### Running incremental backup\n";
	foreach($incrementals as $incremental){
		$toFull=$targetDir.'/incremental/'.$incremental['to'];
		echo "---- From: ".$incremental['from']."\n";
		echo "     To:   $toFull\n";
		if(!prepareFolder($toFull)){
			echo "!!!! ERROR: Cannot create dir $toFull\n";
			$hasWarning=true;
		}
		if($incremental['touch']){
			$command='find '.escapeshellarg($incremental['from']).' -exec touch -d 1971-01-01 "{}" \\;';
			echo "     Touching\n";
			passthru($command,$ret);
			if($ret){
				echo "!!!! WARNING: cannot touch\n";
				echo "!!!! $command";
				$hasWarning=true;
			}
		}
		$command='rdiff-backup --backup-mode --print-statistics --no-compare-inode';
		if(is_array($incremental['exceptions_regexp'])){
		    foreach($incremental['exceptions_regexp'] as $exception){
				$command.=' --exclude-regexp '.escapeshellarg('^'.preg_quote($incremental['from']).'/'.$exception.'$');
		    }
		}
		if(is_array($incremental['exceptions_glob'])){
		    foreach($incremental['exceptions_glob'] as $exception){
				$command.=' --exclude '.escapeshellarg($incremental['from'].'/'.$exception);
		    }
		}
		$command.=' '.escapeshellarg($incremental['from']).' '.escapeshellarg($toFull).' 2>&1';
		echo "$command\n";
		passthru($command,$ret);
		if($ret){
			echo "!!!! ERROR: rdiff reported errors\n";
			echo "!!!! $command";
			$hasWarning=true;
		}
		if($incremental['purgeAfter']){
			echo "     Purging diffs older than {$incremental['purgeAfter']} days\n";
			$command='rdiff-backup --force --remove-older-than '.escapeshellarg($incremental['purgeAfter'].'D').' '.escapeshellarg($toFull).' 2>&1';
			passthru($command,$ret);
			if($ret){
				echo "!!!! ERROR: rdiff reported errors\n";
				echo "!!!! $command";
				$hasWarning=true;
			}
		}
	}
	
	$f=fopen($lastRunFN,'w');
	if(!$f){
		echo "!!!! ERROR: Cannot write $lastRunFN\n";
		$hasWarning=true;
	} else {
		fwrite($f,$DT->format('Y-m-d')."\n");
		fclose($f);
	}
	
	$time=microtime(true)-$time;
	echo "\n#### Done in ".round($time/60)." minutes\n";
	if($hasWarning){
		echo "     With warnings\n";
	}
	
	if($hasWarning){
		die(2);
	}
		
	
	function prepareFolder($folder,$makeClean=false,$exceptLast=false){
		if(!is_dir($folder)){
			if(preg_match('`^(/*[^/].*)/[^/]*$`',$folder,$match)){
				if(!prepareFolder($match[1])){
					return false;
				}
				if($exceptLast){
					return true;
				}
				if(!mkdir($folder)){
					return false;
				}
			} else {
				if(!mkdir($folder)){
					return false;
				}
			}
		}
		if($makeClean){
			return delDir($folder,true);
		}
		return true;
	}
	
	function delDir($folder,$except=false){
		if(!trim($folder)||$folder==='.'||$folder==='..'||$folder==='/'){
			echo "!!!! PANIC: Refusing to delete folder $folder\n";
			die(1);
		}
		if(is_link($folder)){
			if($except){
				echo "!!!! PANIC: delDir link with \$except\n";
				die(1);
			}
			echo "unlink $folder\n";
			if(!unlink($folder)){
				return false;
			}
			return true;
		}
		if(!is_dir($folder)){
			echo "!!!! PANIC: delDir $folder is not a dir\n";
			die(1);
		}
		$dh=opendir($folder);
		if(!$dh){
			return false;
		}
		while($fn=readdir($dh)){
			if(
				$fn==='.' ||
				$fn==='..'
			){
				continue;
			}
			if(
				is_file("$folder/$fn") ||
				is_link("$folder/$fn")
			){
 				if(!unlink("$folder/$fn")){
					echo "unlink $folder/$fn\n";
					return false;
 				}
			} else {
				if(!delDir("$folder/$fn")){
					return false;
				}
			}
		}
		closedir($dh);
		if(!$except){
 			if(!rmdir($folder)){
				echo "rmdir $folder/$fn\n";
				return false;
 			}
		}
		return true;
	}
	
	function keyFromPath($path){
		return preg_replace('`[^a-zA-Z.0-9]`','_',$path).'_'.substr(sha1($path),0,6);
	}
	
	function compareDates(DateTime $d1, DateTime $d2){
		return strcmp($d1->format('Y-m-d'), $d2->format('Y-m-d'));
	}	