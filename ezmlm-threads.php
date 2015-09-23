<?php
// $Id: ezmlm-threads.php,v 1.6 2008-08-25 15:19:15 alexandre_tb Exp $
//
// ezmlm-threads.php - ezmlm-php v2.0
// --------------------------------------------------------------
// Builds, maintains & displays thread caches
// These cache files live in $ezmlm->tmpdir and are serialized
// php objects that can be unserialized and displayed easily
// --------------------------------------------------------------

require_once("ezmlm.php");
require_once("ezmlm-parser.php");
require_once ('ezmlm-langue-fr.php');

// CLASS: ezmlm_threads will build, maintain & display thread files (even if a thread is only 1 msg)
class ezmlm_threads extends ezmlm_php {

	// load: this is the main function that should be called.
	// it first checks to see if the cache files are stale, if they are it calls build
	// other wise it loads them and calls display
	function load($month) {
		if (!is_dir($this->tempdir . "/ezmlm-php-" . $this->listname)) {
			$checksum = $this->tempdir . "/ezmlm-php-" . $this->listname . "-" . $month . "-" . "checksum";
		} else {
			$checksum = $this->tempdir . "/ezmlm-php-" . $this->listname . "/" . $month . "-" . "checksum";
		}
        $md5 = '' ;
		if (!is_file($checksum)) {
			$this->build($month);
		} else {
			$fd = fopen($checksum,"r");
			while (!preg_match('/^md5:/', $md5)) { $md5 = fgets($fd,4096); }
			fclose($fd);
			$md5 = rtrim(preg_replace('/^md5:/', '', $md5), "\n");
			if ($md5 != $this->md5_of_file($this->listdir . "/archive/threads/" . $month)) {
				print "<!-- $md5 ne " . $this->md5_of_file($this->listdir . "/archive/threads/" . $month) . " -->\n";
				$this->build($month);
			}
		}
		$html = $this->display($month); 
		return $html ;
	}

	// display: this loads each cache file sequentially and displays the messages in them
	// there is no checking of checksum's done here so load() is the preferred method to
	// view the threads
	function display($month) {
		$html = '' ;
		$seq = 0;
		if (!is_dir($this->tempdir . "/ezmlm-php-" . $this->listname)) {
			$cache = $this->tempdir . "/ezmlm-php-" . $this->listname . "-" . $month;
		} else {
			$cache = $this->tempdir . "/ezmlm-php-" . $this->listname . "/" . $month;
		}
        // Le lien par date et par thread
        $html .= '[ '.$this->makelink('action=show_month&amp;actionargs[]='.$month, 'par date').' ]' ;
		$months = array(1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
		9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December');
        // remplac� par le tableau globals $mois dans ezmlm.php
		$html .= '<h2>'.FIL_DE_DISCUSSION.' pour '.$GLOBALS['mois'][((int)substr($month,4,2) / 1) -1] .', ' . substr($month,0,4) . '</h2>'."\n";
		
		$html .= '<table class="table_cadre">'."\n";
		$html .= '<tr><th>Num</th><th>De</th><th>Sujet</th><th>Date</th></tr>'."\n";
		$html .= '<tr><td colspan="3"><hr /></td></tr>'."\n";
		$ctc .= 0;

		if (is_file($cache)) {
			include($cache);
		}
		$html .= '<tr><td colspan="3"></td></tr>'."\n";
        $html .= '</table>'."\n";
        
        return $html ;
        }


	function thread_to_html($thread) {
		$html = '';
		$lastdepth = -1;
        $ctc = 0 ;
		$thread_curr = $thread;
        $class = array ('ligne_paire', 'ligne_impaire') ;
		while ($thread_curr != NULL) {
            preg_match ('!/archive/([0-9]*)/([0-9]*)!', $thread_curr->file, $match) ;
            if (!isset($GLOBALS['fichiers_analyses'][$match[1]][$match[2]])) {
                $message = file_get_contents($this->listdir . "/archive/" . $msgdir . "/" . $msgfile) ;
                $mimeDecode = new Mail_mimeDecode($message) ;
                $mailDecode = $mimeDecode->decode() ;
                //$msg = new ezmlm_parser();
                //$msg->parse_file($this->listdir . $thread_curr->file, TRUE);
                
            } else {
                $mailDecode = $GLOBALS['fichiers_analyses'][$match[1]][$match[2]] ;
            }   
            $actionargs = preg_split("/\//", $thread_curr->file);
			$html .= '<tr class="'.$class[$ctc].'">'."\n";
			$html .= '<td>'.$actionargs[2].$actionargs[3].'</td><td>';
			$html .= $this->makelink('action=show_author_msgs&amp;actionargs[]='. 
                    $this->makehash($this->decode_iso($mailDecode->headers['from'])),$this->decode_iso($this->protect_email($mailDecode->headers['from'],TRUE)));
			$html .= '</td>'."\n";
			$html .= '<td><b>';
			//$html .= " <a name=\"" . urlencode($thread_curr->file) . "\">";    A quoi �a sert ?
			for ($i = 0; $i < $thread_curr->depth; $i++) {
				$html .= "&nbsp;&nbsp;";
			}
			if (($this->thread_subjlen > 0) and (strlen($this->decode_iso($mailDecode->headers['subject'])) > $this->thread_subjlen)) {
				$subject = substr($this->decode_iso($mailDecode->headers['subject']), 0, ($this->thread_subjlen - 3 - ($thread_curr->depth * 2)));
				$subject = $subject . "...";
			} else {
				$subject = $this->decode_iso($mailDecode->headers['subject']);
			}

			
			$subject = preg_replace("/\[" . $this->listname . "\]/", "", $subject);
			$html .= $this->makelink("action=show_msg&amp;actionargs[]=" . $actionargs[2] . "&amp;actionargs[]=" . $actionargs[3], $subject);
			$html .= "</b></td>\n";
			$html .= '<td>' .$this->date_francaise($mailDecode->headers['date']).'</td>'."\n";
			$html .= "</tr>\n";

			$ctc++;
			if ($ctc == count($this->tablecolours)) { $ctc = 0; }

			$lastdepth = $thread_curr->depth;
			$thread_curr = $thread_curr->next;
		}

		$html .= '<tr><td colspan="3"><hr noshade size="1" /></td></tr>'."\n";
		return $html;
	}

	// build: takes one argument in the format YYYYMM and builds the thread cache file
	// for that month if the ezmlm thread file exists. The resulting cache file is then
	// stored in $this->tmpdir;
	function build($month) {
		if (!is_file($this->listdir . "/archive/threads/" . $month)) { return FALSE; }

		if (!is_dir($this->tempdir . "/ezmlm-php-" . $this->listname)) {
                        $fd2 = fopen($this->tempdir . "/ezmlm-php-" . $this->listname . "-" . $month,"w+");
                } else {
                        $fd2 = fopen($this->tempdir . "/ezmlm-php-" . $this->listname . "/" . $month,"w+");
                }
		fclose($fd2);
        $i=0;
        // ouverture du fichier thread de ezmlm
        // Ils sont class�s mois par mois
		$fd1 = fopen($this->listdir . "/archive/threads/" . $month, "r");
		while (!feof($fd1)) {
			$line = fgets($fd1,4096);
			if (preg_match('/^[0-9]*\:[a-z]* \[.*/', $line)) {
				// valid ezmlm thread file entry
                
                // On place dans $subjectfile le chemin vers le fichier sujet 
				$subjectfile = preg_replace("/^[0-9]*\:([a-z]*) \[.*/", "\\1", $line);
                $subjectfile = substr($subjectfile,0,2) . '/' . substr($subjectfile,2,18);

				$thread_head = NULL;
				$thread_curr = NULL;
				$thread_temp = NULL;
				$thread_depth = 1;

				if (!is_file($this->listdir . "/archive/subjects/" . $subjectfile)) { continue; }
                // on ouvre le fichier sujet 
                // Celui-ci contient sur la premi�re ligne le hash du sujet puis le sujet
                // sur les autres lignes :
                // num_message:ann�emois:hash_auteur nom_auteur
				$fd2 = fopen($this->listdir . "/archive/subjects/" . $subjectfile, "r");
				while (!feof($fd2)) {
					$line2 = fgets($fd2,4096);
					if (preg_match('/^[0-9]/',$line2)) {
						$msgnum = preg_replace('/^([0-9]*):.*/', '\\1', $line2);
						$msgfile = $msgnum % 100;
                        $msgdir  = (int)($msgnum / 100);
						if ($msgfile < 10) { $msgfile = "0" . $msgfile; }
						//$msg = new ezmlm_parser();
						//$msg->parse_file_headers($this->listdir . "/archive/" . $msgdir . "/" . $msgfile, TRUE);
                        
                        $message = file_get_contents($this->listdir . "/archive/" . $msgdir . "/" . $msgfile) ;
                        $mimeDecode = new Mail_mimeDecode($message) ;
                        $mailDecode = $mimeDecode->decode() ;
                        
                        
                        
                        // On stocke le fichier analys�e pour r�utilisation ult�rieure
                        $GLOBALS['fichiers_analyses'][$msgdir][$msgfile] =  $mailDecode ;
						$msgid = (isset ($mailDecode->headers['message-id']) ? $mailDecode->headers['message-id'] : '');
						$inreply = (isset($mailDecode->headers['in-reply-to']) ? $mailDecode->headers['in-reply-to'] : '');
						$references = (isset ($mailDecode->headers['references']) ? $mailDecode->headers['references'] : '') ;
						$thread_depth = 1;

						if ($thread_head == NULL) {
							$thread_head = new ezmlm_thread(0,'/archive/' . $msgdir . '/' . $msgfile,$msgid);
						} else {
							$thread_curr = new ezmlm_thread($depth,'/archive/' . $msgdir . '/' . $msgfile,$msgid);
							if ($inreply != '') { $thread_curr->inreply = $inreply; }
							if ($references != '') { $thread_curr->references = $references; }
							$thread_head->append($thread_curr);
						}
					}
				}
				fclose($fd2);

				// so now after all that mess $thread_head contains a full thread tree
				// first build the depth of each message based on 'in-reply-to' and 'references'
				unset($thread_temp);
				$thread_temp = NULL;
				$thread_curr =& $thread_head->next;
				while (get_class($thread_curr) == 'ezmlm_thread') {
					unset($thread_temp);
					$thread_temp = NULL;

					if ($thread_curr->inreply != '') { $thread_temp =& $thread_head->find_msgid($thread_curr->inreply); }
					if ($thread_temp == NULL) {
						if ($thread_curr->references != '') {
							$refs = preg_split('/ /', $thread_curr->references);
							$refs = array_pop($refs);
							$thread_temp =& $thread_head->find_msgid($refs);
						}
					}
					if ($thread_temp == NULL) {
						// we couldn't find anything... set depth to 1, the default
						$thread_curr->depth = 1;
					} else {
						// we found a reference, set it to it's depth + 1
						$thread_curr->depth = $thread_temp->depth + 1;
					}
					$thread_curr =& $thread_curr->next;
				}

				// now write it to a temp file named MONTH-SEQ where seq is cronologic sequence order of the thread.
				if (!is_dir($this->tempdir . "/ezmlm-php-" . $this->listname)) {
					@mkdir($this->tempdir . "/ezmlm-php-" . $this->listname, 0755);
					if (!is_dir($this->tempdir . "/ezmlm-php-" . $this->listname)) {
						$fd2 = fopen($this->tempdir . "/ezmlm-php-" . $this->listname . "-" . $month, "a");
					} else {
						$fd2 = fopen($this->tempdir . "/ezmlm-php-" . $this->listname . "/" . $month, "a");
					}
				} else {
					$fd2 = fopen($this->tempdir . "/ezmlm-php-" . $this->listname . "/" . $month, "a");
				}
				fputs($fd2,$this->thread_to_html($thread_head));
				fclose($fd2);
			}
		}

		// finally store our checksum
		if (!is_dir($this->tempdir . "/ezmlm-php-" . $this->listname)) {
			$fd2 = fopen($this->tempdir . "/ezmlm-php-" . $this->listname . "-" . $month . "-" . "checksum","w+");
		} else {
			$fd2 = fopen($this->tempdir . "/ezmlm-php-" . $this->listname . "/" . $month . "-" . "checksum","w+");
		}
		fputs($fd2,"md5:" . $this->md5_of_file($this->listdir . "/archive/threads/" . $month) . "\n");
		fclose($fd2);
		fclose($fd1);

		return TRUE;
	}

	// listmessages: prints out a nice little calendar and displays the message
	// totals for each month. The link jumps to the thread listing.
    // On lit le repetoire archive/threads/ qui contient un fichier par moi avec tous les thread, par sujet 
    // Presentes comme suit 
    // num_thread:hash [taille_du_thread] Sujet du thread (le dernier)
    // les messages sont ranges par leur numero
	function listmessages() {
        if (!is_dir($this->listdir . "/archive/threads/")) {
            return false ;
        }
        
        $res = '<table id="petit_calendrier">'."\n";
        $res .= " <tr>\n";
		$res .= "  <td></td>" ;
        foreach ($GLOBALS['mois'] as $valeur) $res .= '<th>'.$valeur.'</th>' ;
		$res .=" </tr>\n";
        $res .= $this->calendrierMessage();
        $res .= "</table>\n";
        return $res;
        /*
        $threadcount = array();

		$repertoire_archive = opendir($this->listdir . "/archive/");
		$tableau_annee = array();
		while (false !== ($item = readdir($repertoire_archive))) {
			// $item contient les noms des repertoires
			// on ne garde que ceux qui sont des chiffres

			if (preg_match('/[0-9]+/', $item)) {
				// on ouvre le fichier d index de chaque repertoire
				$fichier_index = fopen($this->listdir.'/archive/'.$item.'/index', 'r');
				$compteur = 0 ;
				
				while (!feof($fichier_index)) {
					// On ignore la premiere ligne
					$temp = fgets($fichier_index, 4096);
					// dans la seconde on recupere la date
					$temp = fgets($fichier_index, 4096);
					preg_match('/\t([0-9]+) ([a-zA-Z][a-zA-Z][a-zA-Z]) ([0-9][0-9][0-9][0-9])/', $temp, $match) ;
					if ($match[0] != '') {
						
						$threadmonth = date('n', strtotime($match[0]))  ;
						$threadyear = date('Y', strtotime($match[0])) ;
						$threadcount[$threadyear][$threadmonth]++;
						if (!in_array($threadyear, $tableau_annee)) array_push ($tableau_annee, $threadyear);
					}
				}
				fclose ($fichier_index);
			}
		}
		if (count($threadcount) == 0) return 'Il n\y a pas de messages dans les archives';
        // La partie qui suit, simple, cree la table avec le nombre de message echange chaque mois
		$res = '<table id="petit_calendrier">'."\n";
		$res .= " <tr>\n";
		$res .= "  <td></td>" ;
        foreach ($GLOBALS['mois'] as $valeur) $res .= '<th>'.$valeur.'</th>' ;
		$res .=" </tr>\n";
		arsort($tableau_annee);
		foreach ($tableau_annee as $annee) {
			$res .= " <tr>\n";
			$res .= '  <td class="col_annee">'.$annee.'</td>';
			for ($i = 1; $i <= 12; $i++) {
				$res .= '<td class="mois">';
				if (isset($threadcount[$annee][$i]) && $threadcount[$annee][$i] > 0) {
                    $res .= $this->makelink('action=show_month&amp;actionargs[]='.$annee.($i < 10 ? '0'.$i:$i),$threadcount[$annee][$i]);
				} 
				$res .= '</td>';
			}
			$res .= '</tr>'."\n";
		}
		$res .= "</table>\n";
        return $res ;
        */
	}
	/*
	 *  Cree un fichier liste.calendrierPermanent qui contient  
	 *  le nombre de message par mois pour toutes les annees 
	 *  depuis le debut de la liste sauf la derniere
	 * 
	 */
	function calculeCalendrierPermanent($Annnee = '') {
		$numArchive = $this->getNumArchive();
		$dernierRepertoire = floor($numArchive / 100);
		
		$threadcount = array();
		$tableau_annee = array();
		
		
		for ($rep_courant = $dernierRepertoire; $rep_courant >= 0; $rep_courant--) {
			$fichier_index = file ($this->listdir.'/archive/'.$rep_courant.'/index', FILE_IGNORE_NEW_LINES);

			// On parcours le fichier a l envers
			for ($j = count($fichier_index)-1; $j >= 0; $j-=2) {
				preg_match('/\t([0-9]+) ([a-zA-Z][a-zA-Z][a-zA-Z]) ([0-9][0-9][0-9][0-9])/', $fichier_index[$j], $match) ;
				if ($match[0] != '') {
					$threadmonth = date('n', strtotime($match[0]))  ;
					$threadyear = date('Y', strtotime($match[0])) ;
					if ($Annnee != '') {
						if ($threadyear < date('Y')) {
							$sortir = true;
							break;	
						}
					}  else {
						if ($threadyear == date('Y')) continue;	
					}
					$threadcount[$threadyear][$threadmonth]++;
					if (!in_array($threadyear, $tableau_annee)) array_push ($tableau_annee, $threadyear);
				}
			}
			if ($sortir) break;
		}
		$res = ''; 
		arsort($tableau_annee);
		foreach ($tableau_annee as $annee) {
			$res .= " <tr>\n";
			$res .= '  <td class="col_annee">'.$annee.'</td>';
			for ($i = 1; $i <= 12; $i++) {
				$res .= '<td class="mois">';
				if (isset($threadcount[$annee][$i]) && $threadcount[$annee][$i] > 0) {
                    $res .= $this->makelink('action=show_month&amp;actionargs[]='.$annee.($i < 10 ? '0'.$i:$i),$threadcount[$annee][$i]);
				} 
				$res .= '</td>';
			}
			$res .= '</tr>'."\n";
		}
		return $res;
	}
	function ecrireFichierCalendrier() {
		$html = $this->calculeCalendrierPermanent();
		$f = fopen ('tmp/'.$this->listname.'.calendrier', 'w') ;
		fwrite ($f, $html);
		fclose($f);
		return $html;
	}
	
	function calendrierMessage() {
		$html = '';
		// On ajoute la derniere annee
		$html .= $this->calculeCalendrierPermanent(date ('Y'));
		
		if ($this->isFichierCalendrierExiste()) {
			// S il existe mais qu il est trop vieux, il faut le recalculer
			if ($this->isDoitRecalculerCalendrier()) {
				$annees = $this->getAnneesARecalculer();
				$html .= $this->calculeCalendrierPermanent($annees);
		    }
			$html .= file_get_contents('tmp/'.$this->listname.'.calendrier');
		} else {
			$html .= $this->ecrireFichierCalendrier();
		}	
		return $html;
	}
	
	function isFichierCalendrierExiste() {
		if (file_exists('tmp/'.$this->listname.'.calendrier')) {
			return true;
		}
		return false;
	}
	function isDoitRecalculerCalendrier() {

		if (date ('Y', fileatime('tmp/'.$this->listname.'.calendrier')) != date('Y')) return true;
		return false;
	}
	
	function getAnneesARecalculer() {
		// On suppose que l index de ezmlm est correct
		$anneeFichierCalendrier = date ('Y', fileatime('tmp/'.$this->listname.'.calendrier'));
		return $anneeFichierCalendrier + 1;
	}
	
}

// CLASS: ezmlm-thread is a quick little class to allow us to define
// a structure of the current thread in a single-linked list.
// it's a little messy since php doesn't support pointers like C does
// so we have to use references and a head object to append to the list.
class ezmlm_thread {
	var $next;
	var $depth;
	var $file;
	var $msgid;
	var $inreply;
	var $references;
	function append($thread) {
		$thread_curr =& $this;
		while ($thread_curr->next != NULL) {
			$thread_curr =& $thread_curr->next;
		}
		$thread_curr->next = $thread;
	}
	function &find_msgid($msgid) {
		$thread_curr =& $this;
		while ($thread_curr->next != NULL) {
			if (trim($thread_curr->msgid) == trim($msgid)) { return $thread_curr; }
			$thread_curr =& $thread_curr->next;
		}
		return NULL;
	}
	function ezmlm_thread($depth,$file,$msgid) {
		$this->depth = $depth;
		$this->file  = $file;
		$this->msgid = $msgid;
		$this->next = NULL;
	}
}
?>
