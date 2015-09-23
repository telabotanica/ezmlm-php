<?php
// $Id: ezmlm-repondre.php,v 1.2 2005-09-27 16:43:08 alexandre_tb Exp $
//
// ezmlm-msgdisplay.php - ezmlm-php v2.0
// --------------------------------------------------------------
// Will parse a template (if specified) and display a message.
// Includes a default template.
// --------------------------------------------------------------

require_once("ezmlm.php");
require_once("Mail/mimeDecode.php") ;


class ezmlm_repondre extends ezmlm_php {
	// our template
	var $msgtmpl;
    var $message_rendu ;
	// display: parses a message (using ezmlm_parser) and displays it
	// using a template
    
	function repondre($msgfile) {
        if (!is_file($msgfile)) {
			if (is_file($this->listdir . "/" . $msgfile)) { $msgfile = $this->listdir . "/" . $msgfile; }
			else if (is_file($this->listdir . "/archive/" . $msgfile)) { $msgfile = $this->listdir . "/archive/" . $msgfile; }
			else { return FALSE; }
		}
        $message = file_get_contents($msgfile) ;
        $mimeDecode = new Mail_mimeDecode($message) ;
        $mailDecode = $mimeDecode->decode(array('decode_bodies' => 'true', 'include_bodies' => 'true')) ;
        
        // $msg->msgfile contient le chemin du fichier du mail en partant de la racine
        // Le point d'exclamation est le délimiteur de l'expression régulière
		$relfile = preg_replace('!' . $this->listdir . '!', '', $msgfile);
        
		$a1 = preg_replace('!/archive/(.*)/.*$!', '\1', $relfile);  // $a1 contient le nom du répertoire
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
        
        print '<br />'."\n";
        $this->parse_template($mailDecode, $a2, $a1);
        
        $formulaireReponse = new HTML_formulaireMail('formulaire_reponse', 'post', str_replace('&amp;', '&', $this->forcehref).'&action=repondre&'.
                                                    'actionargs[]='.$a1.'&actionargs[]='.$a2.'&'.PROJET_VARIABLE_ACTION.'='.PROJET_ENVOYER_UN_MAIL_V) ;
        $formulaireReponse->construitFormulaire() ;
        
        $formulaireReponse->addElement ('hidden', 'messageid', $mailDecode->headers['message-id']) ;
        // Ajout de > au début de chaque ligne du message
        $tableau = explode ("\n", $this->message_rendu) ;
        $this->message_rendu = "> ".implode ("\n> ", $tableau) ;
        
        $formulaireReponse->setDefaults(array('mail_corps' => $this->message_rendu,
                                              'mail_titre' => 'Re : '.$this->decode_iso ($mailDecode->headers['subject']))) ;

        print $formulaireReponse->toHTML() ;
        

	}
    
    
	function parse_template(&$mailDecode, $numero_mail, $numero_mois, $num_part = '') {
        static $profondeur = array();
        array_push ($profondeur, $num_part) ;
        $corps = '' ;
        
		if ($mailDecode->ctype_primary == 'multipart') {
            include_once PROJET_CHEMIN_CLASSES.'type_fichier_mime.class.php' ;
			for ($i = 0; $i < count($mailDecode->parts); $i++) {
                switch ($mailDecode->parts[$i]->ctype_secondary) {
                    case 'plain' : 
                    case 'html' : $corps .= $mailDecode->parts[$i]->body ;
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
                    
                    $corps .= '';
                                    
                    if (count ($profondeur) > 0) {
                        array_shift($profondeur) ;
                        //for ($j= 0; $j < count ($profondeur); $j++) $corps .= '&actionargs[]='.$profondeur[$j];
                    }
                    /*$corps .= '&actionargs[]='.$i ;
                    $corps .= '">'.'<img src="'.$mimeType->getCheminIcone().'" alt="'.$nom_piece_jointe.'" />&nbsp;' ;
                    $corps .= $nom_piece_jointe;
                    $corps .= '</a><br />' ;*/
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
                $corps .= '' ;
                
                $this->message_rendu .= preg_replace('/<ezmlm-body>/i', $this->cleanup_body($corps,true), $this->msgtmpl);
            }
        } else {
			if (preg_match('/html/i', $mailDecode->ctype_secondary)) {
                $this->message_rendu .= preg_replace('/<ezmlm-body>/i', $this->cleanup_body($mailDecode->body), $this->msgtmpl);
            } else {
                if (isset ($mailDecode->ctype_parameters['charset']) && $mailDecode->ctype_parameters['charset'] == 'UTF-8') {
                    $this->message_rendu .= preg_replace('/<ezmlm-body>/i', utf8_decode($this->cleanup_body($mailDecode->body)) , $this->msgtmpl);
                } else {
                    
                    $this->message_rendu .= preg_replace('/<ezmlm-body>/i', $this->cleanup_body($mailDecode->body), $this->msgtmpl);
                }
            }
		}
	}

	function ezmlm_repondre() {
		$this->ezmlm_php();
		if (($this->msgtemplate != "") and (is_file($this->msgtemplate))) {
			$fd = fopen($this->msgtemplate, "r");
			while (!feof($fd)) { $this->msgtmpl .= fgets($fd,4096); }
			fclose($fd);
		} else {
			$this->msgtmpl = '<ezmlm-body>';
		}
        $this->msgtmpl_entete = '<dl><ezmlm-headers>
<dt><ezmlm-header-name> :</dt>
<dd><ezmlm-header-value></dd>
</ezmlm-headers>
</dl>' ;
	}

}
