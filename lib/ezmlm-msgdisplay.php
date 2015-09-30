<?php
// $Id: ezmlm-msgdisplay.php,v 1.9 2008-05-23 10:18:37 alexandre_tb Exp $
//
// ezmlm-msgdisplay.php - ezmlm-php v2.0
// --------------------------------------------------------------
// Will parse a template (if specified) and display a message.
// Includes a default template.
// --------------------------------------------------------------

require_once("ezmlm.php");
require_once("Mail/mimeDecode.php") ;

class ezmlm_msgdisplay extends ezmlm_php {
	// our template
	var $msgtmpl;
    var $message_rendu ;
    var $_auth ;
	// display: parses a message (using ezmlm_parser) and displays it
	// using a template
    var $msgfile;
    
    function display($msgfile) {
        if (!is_file($msgfile)) {
            if (is_file($this->listdir . "/" . $msgfile)) { $msgfile = $this->listdir . "/" . $msgfile; }
		else if (is_file($this->listdir . "/archive/" . $msgfile)) { $msgfile = $this->listdir . "/archive/" . $msgfile; }
		else { return FALSE; }
	    }
	$this->msgfile = $msgfile ;
        $message = file_get_contents($msgfile) ;
	// En cours de codage
	// La fonction display retourne tout simplement le source du mail
	// Il n'y a plus d'analyse a ce niveau
	
	return $message;
        $mimeDecode = new Mail_mimeDecode($message) ;
        $mailDecode = $mimeDecode->decode(array('decode_bodies' => 'true', 'include_bodies' => 'true')) ;
        
        // $msg->msgfile contient le chemin du fichier du mail en partant de la racine
        // Le point d'exclamation est le delimiteur de l'expression reguliere
		$relfile = preg_replace('!' . $this->listdir . '!', '', $msgfile);
        
		$a1 = preg_replace('!/archive/(.*)/.*$!', '\1', $relfile);  // $a1 contient le nom du repertoire
		$a2 = preg_replace('!/archive/.*/(.*)$!', '\1', $relfile);  // $a2 contient le nom du fichier
		if (isset($mailDecode->headers['date'])) $msgtime = strtotime(preg_replace ('/CEST/', '', $mailDecode->headers['date']));
        $threadidx = date("Ym", $msgtime);
        if ($a2 <= 10) $numero_precedent = '0'.($a2 - 1) ; else $numero_precedent = ($a2 - 1) ;
        if ($a2 < 9) $numero_suivant = '0'.($a2 + 1) ; else $numero_suivant =  ($a2 + 1);
        // On teste si le message suivant existe
        $decoupe = explode ('/', $msgfile) ;
        
        // Les nom de fichiers sont du format :
        // archive/0/01
        // archive/0/02 ... 0/99 archive/1/01 ...
        
        $nom_fichier = $decoupe[count($decoupe)-1] ;
        $nom_repertoire = $decoupe[count($decoupe)-2] ;
        $repertoire_suivant = $nom_repertoire ; $repertoire_precedent = $nom_repertoire ;
        if ($nom_fichier > 8) {
            $fichier_suivant = $nom_fichier + 1 ;
            if ($nom_fichier == 99) {
                $fichier_suivant = '01' ;
                $repertoire_suivant = $nom_repertoire + 1 ;
            }
        } else {
            $fichier_suivant = '0'.($nom_fichier + 1) ;
        }
        if ($nom_fichier > 10) {
            $fichier_precedent = $nom_fichier - 1 ;
        } else {
            if ($nom_fichier == '01') {
                $fichier_precedent = '99' ;
                $repertoire_precedent = $nom_repertoire - 1 ;
            } else {
                $fichier_precedent = '0'.($nom_fichier - 1) ;
            }
        }
        print $this->parse_entete_mail($mailDecode) ;
		$this->parse_template($mailDecode, $a2, $a1);
        print $this->message_rendu;
        //print '</div>' ;
	}
    
    /**
     * Renvoie les infos des messages suivants
     * 
     *
    */
    function getInfoSuivant() {
		$relfile = preg_replace('!' . $this->listdir . '!', '', $this->msgfile);
	 	$nom_repertoire = preg_replace('!/archive/(.*)/.*$!', '\1', $relfile);
		$nom_fichier = preg_replace('!/archive/.*/(.*)$!', '\1', $relfile);
	       
		$repertoire_suivant = $nom_repertoire;
		
		// On recupere le numero du dernier message
		if (file_exists($this->listdir.'/archnum')) {
			$numero_dernier_message = file_get_contents($this->listdir.'/archnum');
		}
		
		// a partir du nom du fichier
		// et du nom du repertoire, on reconstitue
		// le numero du message stocke dans le fichier d index
		// le message 12 du repertoire 2 a le numero 212
		
		if ($nom_repertoire == '0') {
			$numero_message = $nom_fichier;
		} else {
			$numero_message = $nom_repertoire.$nom_fichier ;
		}
		
		// On ouvre le fichier d index
		$fichier_index = fopen ($this->listdir.'/archive/'.$nom_repertoire.'/index', 'r');
		
		
		$compteur_ligne = 1;
		if (preg_match ('/0([1-9][0-9]*)/', $nom_fichier, $match)) {
			$nom_fichier = $match[1];
			$prefixe = '0' ;
		} else {
			$prefixe = '' ;
		}
		$prefixe = $this->prefixe_nom_message($nom_fichier);
		//echo $numero_message;
		// on cherche la ligne avec le numero du message
		while (!feof($fichier_index)) {
				
				$temp = fgets($fichier_index,4096);
				list($num, $hash, $sujet) = split (':', $temp) ;
				
				if ($num == $numero_message) {
					
					$ligne_message_precedent = $compteur_ligne -2;
					$temp = fgets($fichier_index, 4096);
					$temp = fgets($fichier_index, 4096);
					list ($fichier_suivant,$hash, $sujet) = split(':', $temp);
					
					// Au cas ou est au dernier message du fichier d index
					// il faut ouvrir le suivant
					if (feof($fichier_index)) {
						$repertoire_suivant++;
						if (file_exists($this->listdir.'/archive/'.$repertoire_suivant.'/index')) {
							$fichier_index_suivant = fopen($this->listdir.'/archive/'.$repertoire_suivant.'/index', 'r');
							// on recupere le numero du premier message
							list($fichier_suivant, $hash, $sujet) = split (':', fgets($fichier_index_suivant), 4096);
							fclose ($fichier_index_suivant);
						}
					}
					
					// Si le numero est > 100, il faut decouper et ne retenir
					// que les dizaines et unites
					if ($fichier_suivant >= 100) {
						$decimal = (string) $fichier_suivant;
						$numero = substr($decimal, -2) ;
						$fichier_suivant = $numero ;
					} else {
						if ($fichier_suivant <= 9)$fichier_suivant = '0'.$fichier_suivant;
					}
					
					break;
				}
				
				// On avance d une ligne, la 2e ligne contient date hash auteur
				$temp2 = fgets($fichier_index, 4096);
				$compteur_ligne += 2;
		}
		
		// On utilise $ligne_message_precedent pour recupere le num du message precedent
		// Si $ligne_precedent est negatif soit c le premier message de la liste
		// soit il faut ouvrir le repertoire precedent 
		
		if ($ligne_message_precedent > 0) {
			$compteur = 1;
			rewind($fichier_index);
			while (!feof($fichier_index)) {
				$temp = fgets($fichier_index, 4096);
				if ($ligne_message_precedent == $compteur) {
					list ($fichier_precedent, $hash, $sujet) = split (':', $temp) ; 	
				}
				$compteur++;
			}
			// Le nom du repertoire precedent est le meme que le repertoire courant
			$repertoire_precedent = $nom_repertoire ;
		// Si $ligne_message_precedent est negatif, alors il faut ouvrir
		// le fichier index du repertoire precedent
		// si le nom du repertoire est 0, alors il n y a pas de repertoire precedent
		// et donc pas de message precedent
		} else {
			
			if ($nom_repertoire != '0') {
				$repertoire_precedent = $nom_repertoire -1 ;
				// on ouvre le fichier d index et on extraie le numero
				// du dernier message
				
				$fichier_index_precedent = fopen ($this->listdir.'/archive/'.$repertoire_precedent.'/index', 'r') ;
				while (!feof($fichier_index_precedent)) {
					$temp = fgets($fichier_index_precedent,4096);
					$ligne = split (':', $temp) ;
					if ($ligne[0] != '') $fichier_precedent = $ligne[0];
					$temp = fgets($fichier_index_precedent,4096);
				}
				
				fclose ($fichier_index_precedent);
			// on se situe dans le repertoire 0 donc pas de message precedent
			} else {
				$fichier_precedent = null;
				$repertoire_precedent = null;
			}
		}
		if ($fichier_precedent > 100) {
			$decimal = (string) $fichier_precedent;
			$numero = substr($decimal, -2) ;
			$fichier_precedent = $numero ;
		} else {
			if ($fichier_precedent < 10 )$fichier_precedent = '0'.$fichier_precedent;
		}
		fclose ($fichier_index);
		//if ($fichier_precedent != null && $fichier_precedent < 10) $fichier_precedent = '0'.$fichier_precedent;
	
		return array ('fichier_suivant' => $fichier_suivant,
			      'repertoire_suivant' => $repertoire_suivant,
			      'fichier_precedent' => $fichier_precedent,
			      'repertoire_precedent' => $repertoire_precedent);
	}
	
    /**
    *   analyse l'entete d'un mail pour en extraire les ent�tes
    *   to, from, subject, date
    *   met � jour la variable $this->msgtmpl
    *
    */
    
    function parse_entete_mail (&$mailDecode) {
        $startpos = strpos(strtolower($this->msgtmpl_entete), '<ezmlm-headers>');
        $endpos = strpos(strtolower($this->msgtmpl_entete), '</ezmlm-headers>');
		$headers = substr($this->msgtmpl_entete,$startpos + 15,($endpos - $startpos - 15));
        $headers_replace = '' ;
		for ($i = 0; $i < count($this->showheaders); $i++) {
		    $val = $this->showheaders[$i];
		    $headers_replace .= $headers;
		    $hnpos = strpos(strtolower($headers_replace), '<ezmlm-header-name>');
		    $headers_replace = substr_replace($headers_replace, $this->header_en_francais[$val], $hnpos, 19);
		    $hvpos = strpos(strtolower($headers_replace), '<ezmlm-header-value');
            $headers_replace = $this->decode_iso ($headers_replace) ;
            switch ($val) {
            	case 'date':
            	$headers_replace = substr_replace($headers_replace, $this->date_francaise($mailDecode->headers[strtolower($val)]), $hvpos, 20);
            	break;
            	case 'from':
            	if ($mailDecode->headers[strtolower($val)] == '') $from = $mailDecode->headers['return-path'] ;
            		else $from = $mailDecode->headers['from'];
            	$headers_replace = substr_replace($headers_replace, $this->protect_email($this->decode_iso($from)), $hvpos, 20);
            	//$headers_replace = htmlspecialchars($headers_replace);
            	break;
            	default:
            	$headers_replace = substr_replace($headers_replace, $this->protect_email($this->decode_iso($mailDecode->headers[strtolower($val)])), $hvpos, 20);
            }
		}
        return substr_replace($this->msgtmpl_entete, $headers_replace, $startpos, (($endpos + 16) - $startpos));
    }
    
    
	function parse_template(&$mailDecode, $numero_mail, $numero_mois, $num_part = '') {
        static $profondeur = array();
        if ($num_part != '') array_push ($profondeur, $num_part) ;
        $corps = '' ;
        
		if ($mailDecode->ctype_primary == 'multipart') {
            include_once PROJET_CHEMIN_CLASSES.'type_fichier_mime.class.php' ;
			for ($i = 0; $i < count($mailDecode->parts); $i++) {
                switch ($mailDecode->parts[$i]->ctype_secondary) {
                    case 'plain' : 
                    if ($mailDecode->parts[$i]->headers['content-transfer-encoding'] == '8bit') {
                    	$corps .= $this->_cte_8bit($mailDecode->parts[$i]->body);
                    } else if ($mailDecode->parts[$i]->headers['content-transfer-encoding'] == 'quoted-printable') {
                    	if ($mailDecode->parts[$i]->ctype_parameters['charset'] == 'UTF-8') {
                    		$corps .= utf8_decode($mailDecode->parts[$i]->body);	
                    	} else {
                    		// Si un multipart/related, qu'on ne sait pas decoder, contient une partie plain
                    		// qui n'est pas en UTF-8, faut bien la recuperer... cela dit, comprend pas comment
                    		// ça marche dans les autres cas, hors UTF-8
                    		$corps .= $mailDecode->parts[$i]->body;
                    	}
                    }
                    break;
                    case 'related':
                    	// patch pourri : comme "multipart/related" n'est pas gere, on ignore la partie
                    	// (se produit apparemment lorsqu'une signature avec image est envoyee, par Thunderbird
                    	// sous Windows en tout cas)
                    	break;
                    case 'html' : $corps .= trim(strip_tags ($mailDecode->parts[$i]->body, '<br><p><a><style>'));
                    break ;
                    case 'mixed' : 
                    case 'rfc822' :
                    case 'alternative' :
                    case 'appledouble' :
                        $this->parse_template($mailDecode->parts[$i], $numero_mail, $numero_mois, $i) ;
                    break ;
                    case 'applefile' : continue ;
                    break ;
                    default : 
                    
                    if ($mailDecode->parts[$i]->ctype_secondary == 'octet-stream') {
                        $nom_piece_jointe = $mailDecode->parts[$i]->ctype_parameters['name'] ;
                        $tab = explode ('.', $nom_piece_jointe) ;
                        $extension = $tab[count ($tab) - 1] ;
                        $mimeType = type_fichier_mime::factory($extension);
                        $mimeType->setCheminIcone(PROJET_CHEMIN_ICONES) ;
                    } else {
                        $nom_piece_jointe = isset ($mailDecode->parts[$i]->d_parameters['filename']) ? 
                                            $mailDecode->parts[$i]->d_parameters['filename'] : $mailDecode->parts[$i]->ctype_parameters['name'] ;
                        $mimeType = new type_fichier_mime( $mailDecode->parts[$i]->ctype_primary.'/'.
                                            $mailDecode->parts[$i]->ctype_secondary, PROJET_CHEMIN_ICONES) ;
                    }
                    $lien = PROJET_CHEMIN_APPLI.'fichier_attache.php?nom_liste='.$this->listname.
                                    '&actionargs[]='.$numero_mois.
                                    '&actionargs[]='.$numero_mail;
                    if (count ($profondeur) > 0) {
                        array_shift($profondeur) ;
                        for ($j= 0; $j < count ($profondeur); $j++) $lien .= '&actionargs[]='.$profondeur[$j];
                    } 
                    $lien .= '&actionargs[]='.$i ;
                    $corps .= '<a href="'.$lien.'">';
					
					$tableau_type_image = array ('jpg', 'jpeg', 'pjpeg');
                    
                    if (in_array ($mailDecode->parts[$i]->ctype_secondary, $tableau_type_image)) {
                    	$corps .= '<img src="'.$lien.'&amp;min=1" alt="'.$nom_piece_jointe.'" />&nbsp;' ;
                    	$texte_lien = '';
                    } else {
                    	$corps .= '<img src="'.$mimeType->getCheminIcone().'" alt="'.$nom_piece_jointe.'" />&nbsp;' ;
                    	$texte_lien = $nom_piece_jointe;
                    }
                    $corps .= $texte_lien;
                    $corps .= '</a><br />' ;
                    break ;
                }
            }
            $this->message_rendu .= preg_replace('/<ezmlm-body>/i', $this->cleanup_body($corps,TRUE), $this->msgtmpl);
            
		} else if ($mailDecode->ctype_primary == 'message') {
            
            $this->message_rendu .= "\n".'<div class="message">'.$this->parse_entete_mail($mailDecode->parts[0]);
            $corps .= $this->parse_template($mailDecode->parts[0], $numero_mail, $numero_mois, 0) ;
            $this->message_rendu .= preg_replace('/<ezmlm-body>/i', $this->cleanup_body($corps,true), $this->msgtmpl).'</div>';
            
        } else if ($mailDecode->ctype_primary == 'application' || $mailDecode->ctype_primary == 'image'){
            if ($mailDecode->ctype_secondary == 'applefile') return ;
            $mimeType = new type_fichier_mime( $mailDecode->ctype_primary.'/'.$mailDecode->ctype_secondary,PROJET_CHEMIN_ICONES) ;
            
            if ($mimeType->getIdType() != 12) {
                $corps .= '<a href="'.PROJET_CHEMIN_APPLI.'fichier_attache.php?nom_liste='.$this->listname.'&actionargs[]='.
                                    $numero_mois.'&actionargs[]='.
                                    $numero_mail.'&actionargs[]='.$i.'">'.
                                    '<img src="'.$mimeType->getCheminIcone().'" alt="'.$mailDecode->ctype_parameters['name'].'" />&nbsp;' ;
                $corps .= $mailDecode->ctype_parameters['name'].'</a><br />' ;
                
                $this->message_rendu .= preg_replace('/<ezmlm-body>/i', $this->cleanup_body($corps,true), $this->msgtmpl);
            }
        } else {
			if (preg_match('/html/i', $mailDecode->ctype_secondary)) {
                $this->message_rendu .= preg_replace('/<ezmlm-body>/i', $this->cleanup_body($mailDecode->body,TRUE), $this->msgtmpl);
            } else {
                if (isset ($mailDecode->ctype_parameters['charset']) && $mailDecode->ctype_parameters['charset'] == 'UTF-8') {
                    $this->message_rendu .= preg_replace('/<ezmlm-body>/i', '<pre>' . utf8_decode($this->cleanup_body($mailDecode->body,TRUE)) . '</pre>', $this->msgtmpl);
                } else {
                    $this->message_rendu .= preg_replace('/<ezmlm-body>/i', '<pre>' . $this->cleanup_body($mailDecode->body,TRUE) . '</pre>', $this->msgtmpl);
                }
            }
		}
		array_pop ($profondeur);
	}	

	function ezmlm_msgdisplay() {
		$this->ezmlm_php();
		if (($this->msgtemplate != "") and (is_file($this->msgtemplate))) {
			$fd = fopen($this->msgtemplate, "r");
			while (!feof($fd)) { $this->msgtmpl .= fgets($fd,4096); }
			fclose($fd);
		} else {
			$this->msgtmpl = '<pre>
<ezmlm-body>
</pre>
        ';
		}
        $this->msgtmpl_entete = '<dl><ezmlm-headers>
<dt><ezmlm-header-name> :</dt>
<dd><ezmlm-header-value></dd>
</ezmlm-headers>
</dl>' ;
	}
	
		// _cte_8bit: decode a content transfer encoding of 8bit
	// NOTE: this function is a little bit special. Since the end result will be displayed in
	// a web browser _cte_8bit decodes ASCII characters > 127 (the US-ASCII table) into the
	// html ordinal equivilant, it also ensures that the messages content-type is changed
	// to include text/html if it changes anything...
	function _cte_8bit($data,$simple = FALSE) {
		if ($simple) { return $data; }
		$changed = FALSE;
		$out = '';
		$chars = preg_split('//',$data);
		while (list($key,$val) = each($chars)) {
			if (ord($val) > 127) { $out .= '&#' . ord($val) . ';'; $changed = TRUE; }
			else { $out .= $val; }
		}
		if ($changed) { $this->headers['content-type'][1] = 'text/html'; }
		return $out;
	}

}
