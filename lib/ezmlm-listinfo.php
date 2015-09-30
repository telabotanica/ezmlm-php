<?php
// $Id: ezmlm-listinfo.php,v 1.5 2008-11-04 17:11:10 aperonnet Exp $
//
// ezmlm-listinfo.php - ezmlm-php v2.0
// --------------------------------------------------------------
// Displays general list info in the format of a welcome page.
// --------------------------------------------------------------

require_once("ezmlm.php");

class ezmlm_listinfo extends ezmlm_php {

    function ezmlm_listinfo () {
        return is_dir($this->listdir.'/archive/0') ;
    }
	function display() {
        
        if (!is_dir($this->listdir.'/archive/0')) {  // On teste si il y a au moins un message, cad le rï¿½pertoire 0
            echo $this->listdir.'/archive/0' ;
            
            return false ;
        }
        
		$parser = new ezmlm_parser();
        $parser->listdir = $this->listdir ;
		
		//$this->show_info_file();
		
        
		$threads = new ezmlm_threads();
        $threads->listdir = $this->listdir ;
        $threads->listname = $this->listname ;
        $threads->forcehref = $this->forcehref ;        /// ajout alex
        $threads->listmessages() ;
		$this->show_recentmsgs();
        return true ;
	}

	function show_info_file() {
		if (@is_file($this->listdir . "/text/info")) {
			$infofile = @file($this->listdir . "/text/info");
			while (list($line_num, $line) = each($infofile)) {
				print nl2br($line);
			}
		}
	}


	function show_recentmsgs($title = "Messages rï¿½cents") {
        
        if (!is_dir($this->listdir.'/archive/0')) return false;
        
        
        $html = '' ;
		$parser = new ezmlm_parser();
        $parser->listdir = $this->listdir ;
        $html .= '<table class="table_cadre">'."\n";
        $html .= '<tr><th class="col1">Num</th><th>De</th><th>Sujet</th><th>Date</th></tr>'."\n";
        $ctc = 0;
        $recent = $parser->recent_msgs();
        
        // le tableau recent est de la forme
        // $recent[numero_message][1] sujet
        // $recent[numero_message][2] date en anglais => (22 May 2006)
        // $recent[numero_message][3] le hash de l auteur
        // $recent[numero_message][4] auteur
        
        $class = array ('ligne_paire', 'ligne_impaire') ;
        
        while (list($key,$val) = each($recent)) {
            $html .= '<tr class="'.$class[$ctc].'">'."\n";
            //print '<td>'.$val->nummessage.'</td>' ;
            
            // $key contient le numero du message tel que dans les fichiers d index par ex 216
            // on retrouve le nom du repertoire et le nom du fichier
            $decimal = (string) $key;
            if ($key >= 100) {
					$fichier_message = substr($decimal, -2) ;
					$repertoire_message = substr ($decimal, 0, -2) ;
				} else {
					if ($key < 10) {
						$fichier_message = '0'.$key;
					} else {
						$fichier_message = $decimal;
					}
					$repertoire_message = '0';
			}
            
            $html .= '<td>'.$key.'</td>' ;
            $html .= '<td>';

            $from = $val[4];

            $html .= $this->makelink("action=show_author_msgs&actionargs[]=".$val[3],$this->decode_iso($this->protect_email($from,false)));
            $html .= "</td>\n";
            $html .= '<td><b>';
            $actionargs = preg_split("/\//", $val->msgfile);
            
            $html .= $this->makelink("action=show_msg&actionargs[]=".$repertoire_message.
                                "&actionargs[]=".$fichier_message ,$this->decode_iso($val[1]));

            $html .= "</b></td>\n";
            
            //print '<td>'.$this->date_francaise($val[2]).'</td>'."\n";
            $html .= '<td>'.$val[2].'</td>'."\n";
            $html .= "</tr>\n";

            $ctc++;
            if ($ctc == 2) { $ctc = 0; }
        }
        $html .= '</table>'."\n";
        return $html;
	}
    
    function show_month ($month) {
        // Le nom du fichier est annï¿½emoi ex 200501 pour janvier 2005
        
        // le html est vide au début
        $html = '' ;
        
        // on ouvre chaque fichier en lecture
        if(!file_exists($this->listdir . '/archive/threads/' . $month)) {
        	return false ;
        }
        $fd = file_get_contents($this->listdir . '/archive/threads/' . $month, 'r');
        $fichier = explode ("\n", $fd) ;
        // on rï¿½cupï¿½re la premiï¿½re ligne
        $premiere_ligne = $fichier[0] ;
        $derniere_ligne = $fichier[count($fichier)-2];
        
        
        preg_match ('/[0-9]+/', $premiere_ligne, $match) ;
        $numero_premier_mail  = $match[0] ;
        
        preg_match ('/[0-9]+/', $derniere_ligne, $match1) ;
        $numero_dernier_mail  = $match1[0] ;
		
		// On cherche le rï¿½pertoire du premier mail
        
        $repertoire_premier_mail = (int) ($numero_premier_mail / 100) ;
        
        // petite verification de coherence
        if ($numero_premier_mail > $numero_dernier_mail) {
        	$temp = $numero_premier_mail;
        	$numero_premier_mail = $numero_dernier_mail ;
        	$numero_dernier_mail = $temp;
        }
        $html .= '<table class="table_cadre">'."\n";
        $html .= '<tr><th class="col1">Num</th><th>De</th><th>Sujet</th><th>Date</th></tr>'."\n";
        $ctc = 0;
        
        $class = array ('ligne_paire', 'ligne_impaire') ;
        
        for ($i = $numero_premier_mail, $compteur = $numero_premier_mail ; $compteur <= $numero_dernier_mail; $i++, $compteur++) {
            if ($i > 99) {
                $multiplicateur = (int) ($i / 100) ;
                // pour les nails > 99, on retranche n fois 100, ex 256 => 56 cad 256 - 2 * 100 
                $i = $i - $multiplicateur * 100 ;
            }
            if ($i < 10) $num_message = '0'.$i ; else $num_message = $i ;
            if (file_exists($this->listdir.'/archive/'.$repertoire_premier_mail.'/'.$num_message)) { 
	            $mimeDecode = new Mail_mimeDecode(file_get_contents ($this->listdir.'/archive/'.$repertoire_premier_mail.'/'.$num_message)) ;
        	    $mailDecode = $mimeDecode->decode() ;
            	if ($i == 99) {
                	$repertoire_premier_mail++;
               	 $i = -1;
            	}
            
           	 	$html .= '<tr class="'.$class[$ctc].'">'."\n";
            	$html .= '<td>'.($repertoire_premier_mail != 0 ? $repertoire_premier_mail : '').$num_message.'</td><td>';
            	$hash = $this->makehash($mailDecode->headers['from']);
            
            	$html .= $this->makelink("action=show_author_msgs&actionargs[]=".
            			$hash,$this->decode_iso($this->protect_email($mailDecode->headers['from'],TRUE)));
            	$html .= "</td>\n";
            	$html .= '<td><b>';
            	$actionargs[0] = $repertoire_premier_mail ;
            	$actionargs[1] = $num_message ;
            
           	 	if (count ($actionargs) > 1) {
                	$html .= $this->makelink("action=show_msg&actionargs[]=".
                			$actionargs[(count($actionargs) - 2)] . 
                            "&actionargs[]=".
                            $actionargs[(count($actionargs) - 1)] ,$this->decode_iso($mailDecode->headers['subject']));
            	}
            	$html .= "</b></td>\n";
           	 	$html .= '<td>'.$this->date_francaise($mailDecode->headers['date']).'</td>'."\n";
            	$html .= "</tr>\n";
            	$ctc++;
            	if ($ctc == 2) { $ctc = 0; }
			}
        }
        $html .= '</table>'."\n";
        return $html;
    }
}
?>
