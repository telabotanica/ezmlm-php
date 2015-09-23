<?php
// $Id: ezmlm-author.php,v 1.3 2007/04/19 15:34:35 neiluj Exp $
//
// ezmlm-author.php - ezmlm-php v2.0
// --------------------------------------------------------------
// Displays all messages by a given author
// --------------------------------------------------------------

require_once("ezmlm.php");

class ezmlm_author extends ezmlm_php {
	function display($authorhash) {
		$file = "/archive/authors/" . substr($authorhash,0,2) . "/" . substr($authorhash,2,18);
        //echo $file ;
		if (!is_file($this->listdir . $file)) { $this->error(EZMLM_INVALID_AUTHOR); return; }
        // Le fichier author comprend
        // Premi�re ligne hash_auteur nom_auteur
        // num_mess:ann�emois:hash_sujet sujet
		$fd = @fopen($this->listdir . $file, "r");
        $i = 0 ;
        $class = array ('ligne_impaire', 'ligne_paire') ;
		while (!feof($fd)) {
			$buf = fgets($fd,4096);
			if (preg_match('/^' . $authorhash . '/', $buf)) {
				// this should ALWAYS be the first line in the file
				$author = preg_replace('/^' . $authorhash . ' /', '', $buf);
                print '<h3>'.$author.'</h3>' ;
				print '<table class="table_cadre">'."\n";
                print '<tr><th class="col1">De</th><th>Sujet</th><th>Date</th></tr>'."\n";
				$tableopened = TRUE;
			} else if (preg_match('/^[0-9]*:[0-9]/',$buf)) {
				// si la ligne est valide
                // on r�cup�re le num�ro du message pour en extraire le nom du fichier
				$msgfile = preg_replace('/^([0-9]*):.*/', '\1', $buf);
				$msgdir  = (int)((int)$msgfile / 100);
				$msgfile = (int)$msgfile % 100;

				if ($msgfile < 10) { $msgfile = "0" . $msgfile; }

				if (!is_file($this->listdir . "/archive/" . $msgdir . "/" . $msgfile)) {
					print "<!-- " . $this->listdir . "/archive/" . $msgdir . "/" . $msgfile . " -->\n";
					$this->error(EZMLM_INCONSISTANCY);	
					fclose($fd);
					return;
				}

				//$msg = new ezmlm_parser();
				//$msg->parse_file_headers($this->listdir . "/archive/" . $msgdir . "/" . $msgfile);
                
                $message = file_get_contents($this->listdir . "/archive/" . $msgdir . "/" . $msgfile) ;
                $mimeDecode = new Mail_mimeDecode($message) ;
                $mailDecode = $mimeDecode->decode() ;
                
				$subject = $mailDecode->headers['subject'];	
				$subject = preg_replace("/\[" . $this->listname . "\]/", "", $subject);
                $date = preg_replace ('/CEST/', '', $mailDecode->headers['date']);	
				print '<tr class="'.$class[$i].'">'."\n";
                if ($mailDecode->headers['from'] == '') $from = $mailDecode->headers['return-path'] ; else $from = $mailDecode->headers['from'];
                $hash = $this->makehash($from);
                print '<td>'.$this->makelink("action=show_author_msgs&actionargs[]=" . $hash,$this->decode_iso($this->protect_email($from,false)));
                print '</td>';
                print "<td><b>" . $this->makelink("action=show_msg&actionargs[]=" . $msgdir . "&actionargs[]=" . $msgfile, $this->decode_iso($subject)) . "</b></td>";
				print "<td>" . $this->date_francaise($mailDecode->headers['date']) . "</td>\n";
				print "</tr>\n";
                $i++;
                if ($i == 2) $i = 0 ;
                unset ($mailDecode) ;
			}
		}
		if ($tableopened) { print "</table>\n"; }
	}
}
