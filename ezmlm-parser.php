<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */ 
// +------------------------------------------------------------------------------------------------------+
// | PHP version 4.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2004 Tela Botanica (accueil@tela-botanica.org)                                         |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU General Public                                                  |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | General Public License for more details.                                                             |
// |                                                                                                      |
// | You should have received a copy of the GNU General Public                                            |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// CVS : $Id: ezmlm-parser.php,v 1.3 2007/04/19 15:34:35 neiluj Exp $
/**
* Application projet
*
* classe ezmlm_parser pour lire les fichiers d index de ezmlm-idx
*
*@package projet
//Auteur original : ?? recupere dans ezmlm-php
*@author        Alexandre Granier <alexandre@tela-botanica.org>
*@copyright     Tela-Botanica 2000-2004
*@version       $Revision: 1.3 $
// +------------------------------------------------------------------------------------------------------+
*/


// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

// $Id: ezmlm-parser.php,v 1.3 2007/04/19 15:34:35 neiluj Exp $
//

require_once("ezmlm.php");
require_once("Mail/mimeDecode.php") ;
// CLASS: ezmlm-parser
class ezmlm_parser extends ezmlm_php {
    var $headers;           // the full untouched headers of the message
    var $body;              // the full untouched (but decoded) body (this is not $this->parts[0]->body)
	var $parts;		// all the parts, if it is a multipart message. each part is an ezmlm_parser object...

    // Here's the most accessed headers, everything else can be
    // accessed from the $this->headers array.
    var $to;                // To:
    var $from;              // From:
    var $date;              // Date:
    var $subject;           // Subject:
    var $replyto;           // Reply-To:
    var $contenttype;       // Content-Type:

	var $multipart;		// TRUE if the message is a multipart message

	var $msgfile;		// if parsed from a file, this is the filename...

	// functions

	/**
     * recent_msgs renvoie les derniers messages de la liste de discussion
     * ezmlm
     *
     * (
     * [0] => Array
     *   (
     *       [1] => sujet
     *       [2] => date en anglais
     *       [3] => le hash de l auteur
     *       [4] => l auteur
     *   )
     * [1] => ...
     * )
     * @param	int	le nombre de message a renvoye
     * @return array	un tableau contenant les messages
     * @access public
     */
	function recent_msgs($show = 20, $month = "") {
		
		$repertoire_archive = opendir($this->listdir . "/archive/");

		$repertoire_message = array() ;
		$dernier_repertoire = 0 ;
		while (false !== ($item = readdir($repertoire_archive))) {
			// $item contient les noms des repertoires
			// on ne garde que ceux qui sont des chiffres

			if (preg_match('/[0-9]+/', $item)) {
				// on ouvre le fichier d index de chaque repertoire
				if ((int) $item > $dernier_repertoire) $dernier_repertoire = (int) $item;
			
			}
		}
		$tableau_message = array() ;
		$compteur_message = 0 ;
		$fichier_index = fopen ($this->listdir.'/archive/'.$dernier_repertoire.'/index', 'r') ;
		while (!feof($fichier_index)) {
				// Recuperation du numero de message, du hash du sujet et du sujet
				$temp = fgets($fichier_index, 4096);
				preg_match('/([0-9]+): ([a-z]+) (.*)/', $temp, $match) ;
				
				// dans la seconde on recupere la date, hash auteur et auteur
				$temp = fgets($fichier_index, 4096);
				preg_match('/\t([0-9]+) ([a-zA-Z][a-zA-Z][a-zA-Z]) ([0-9][0-9][0-9][0-9]) ([^;]+);([^ ]*) (.*)/', $temp, $match_deuxieme_ligne) ;
				if ($match[1] != '') {
				$tableau_message[$match[1]] = array ($match[2], $match[3], 
									$match_deuxieme_ligne[1].' '.$match_deuxieme_ligne[2].' '.$match_deuxieme_ligne[3], 
									$match_deuxieme_ligne[5], 
									$match_deuxieme_ligne[6]);
				}
			}
			fclose ($fichier_index);
		// on renverse le tableau pour afficher les derniers messages en premier
		$tableau_message = array_reverse($tableau_message, true);
		
		// On compte le nombre de message, s il est inferieur $show et que l on est
		// pas dans le premier index, on ouvre le fichier precedent et recupere
		// le n dernier message
		
		if (count ($tableau_message) < $show && $dernier_repertoire != '0') {
			$avant_dernier_repertoire = $dernier_repertoire - 1 ;
			// On utilise file_get_contents pour renverser le fichier
			$fichier_index = array_reverse(
									explode ("\n", 
										preg_replace ('/\n$/', '', 
											file_get_contents ($this->listdir.'/archive/'.$avant_dernier_repertoire.'/index')) ), true) ;
			reset ($fichier_index);
			//var_dump ($fichier_index);
			
			for ($i = count ($tableau_message); $i <= $show; $i++) {
				// Recuperation du numero de message, du hash du sujet et du sujet
				// dans la seconde on recupere la date, hash auteur et auteur

				preg_match('/\t([0-9]+) ([a-zA-Z][a-zA-Z][a-zA-Z]) ([0-9][0-9][0-9][0-9]) ([^;]+);([^ ]*) (.*)/', 
									current ($fichier_index), $match_deuxieme_ligne) ;
				preg_match('/([0-9]+): ([a-z]+) (.*)/', next($fichier_index), $match) ;
				next ($fichier_index);
				
				if ($match[1] != '') {
				$tableau_message[$match[1]] = array ($match[2], $match[3], 
									$match_deuxieme_ligne[1].' '.$match_deuxieme_ligne[2].' '.$match_deuxieme_ligne[3], 
									$match_deuxieme_ligne[5], 
									$match_deuxieme_ligne[6]);
				}
			}
		} else {
			// Si le nombre de message est > $show on limite le tableau de retour
			$tableau_message = array_slice($tableau_message, 0, $show, true);
		}
			
		
		return $tableau_message ;
	}


	// parse_file - opens a file and feeds the data to parse, file can be relative to the listdir
	function parse_file($file,$simple = FALSE) {
		if (!is_file($file)) {
			if (is_file($this->listdir . "/" . $file)) { $file = $this->listdir . "/" . $file; }
			else if (is_file($this->listdir . "/archive/" . $file)) { $file = $this->listdir . "/archive/" . $file; }
			else { return FALSE; }
		}

		$this->msgfile = $file;
        $data = '' ;
		$fd = fopen($file, "r");
		while (!feof($fd)) { $data .= fgets($fd,4096); }
		fclose($fd);
		return $this->parse($data,$simple);
	}

    // parse_file_headers - ouvre un fichier et analyse les ent�tes
	function parse_file_headers($file,$simple = FALSE) {
		if (!is_file($file)) {
			if (is_file($this->listdir . "/" . $file)) { $file = $this->listdir . "/" . $file; }
			else if (is_file($this->listdir . "/archive/" . $file)) { $file = $this->listdir . "/archive/" . $file; }
			else { return FALSE; }
		}

		$this->msgfile = $file;
        $data = file_get_contents ($file) ;
        $message = file_get_contents($file) ;
        $mimeDecode = new Mail_mimeDecode($message) ;
        $mailDecode = $mimeDecode->decode() ;
        return $mailDecode ;
	}

	// this does all of the work (well it calls two functions that do all the work :)
	// all the decoding a part breaking follows RFC2045 (http://www.faqs.org/rfcs/rfc2045.html)
	function parse($data,$simple = FALSE) {
        
		if (($this->_get_headers($data,$simple)) && $this->_get_body($data,$simple)) { return TRUE; }
		return FALSE;
	}

	// all of these are internal functions, you shouldn't call them directly...

	// _ct_parse: parse Content-Type headers -> $ct[0] = Full header, $ct[1] = Content-Type, $ct[2] ... $ct[n] = AP's
	function _ct_parse() {
		$instr = $this->headers['content-type'];
		preg_replace('/\(.*\)/','',$instr); // strip rfc822 comments
		if (preg_match('/: /', $instr)) {
			$ct = preg_split('/:/',trim($instr),2);
			$ct = preg_split('/;/',trim($ct[1]));
		} else {
			$ct = preg_split('/;/',trim($instr));
		}
		if (isset($ct[1])) $attrs = preg_split('/[\s\n]/',$ct[1]);
		$i = 2;
		$ct[1] = $ct[0];
		$ct[0] = $this->headers['content-type'];
        if (isset($attrs) && is_array($attrs)) {
            while (list($key, $val) = each($attrs)) {
                if ($val == '') continue;
                $ap = preg_split('/=/',$val,2);
                if (preg_match('/^"/',$ap[1])) { $ap[1] = substr($ap[1],1,strlen($ap[1])-2); }
                $ct[$i] = $ap;
                $i++;
            }
        }
		// are we a multipart message?
		if (preg_match('/^multipart/i', $ct[1])) { $this->multipart = TRUE; }

		return $ct;
	}

	// _get_headers: pulls the headers out of the data and builds the $this->headers array
	function _get_headers($data,$simple = FALSE) {
		$lines = preg_split('/\n/', $data);
		while (list($key, $val) = each($lines)) {
			$val = trim($val);
			if ($val == "") break;
			if (preg_match('/^From[^:].*$/', $val)) continue;	/* strips out any From lines added by the MTA */

			$hdr = preg_split('/: /', $val, 2);
			if (count($hdr) == 1) {
				// this is a continuation of the last header (like a recieved from line)
				$this->headers[$last] .= $val;
			} else {
				$this->headers[strtolower($hdr[0])] = $hdr[1];
                //echo htmlspecialchars($this->headers['from'])."<br />" ;
				$last = strtolower($hdr[0]);
			}
		}
        // ajout alex
        // pour supprimer le probl�me des ISO...
        // a d�placer ailleur, et appel� avant affichage
        
        if (preg_match ('/windows-[0-9][0-9][0-9][0-9]/', $this->headers['subject'], $nombre)) {
            $reg_exp = $nombre[0] ;
        } else {
            $reg_exp = 'ISO-8859-15?' ;
        }
        if (preg_match ('/UTF/i', $this->headers['subject'])) $reg_exp = 'UTF-8' ;
        preg_match_all ("/=\?$reg_exp\?(Q|B)\?(.*?)\?=/i", $this->headers['subject'], $match, PREG_PATTERN_ORDER)  ;
        for ($i = 0; $i < count ($match[0]); $i++ ) {
            
                if ($match[1][$i] == 'Q') {
                    $decode = quoted_printable_decode ($match[2][$i]) ;
                } elseif ($match[1][$i] == 'B') {
                    $decode = base64_decode ($match[2][$i]) ;
                }
                $decode = preg_replace ("/_/", " ", $decode) ;
            if ($reg_exp == 'UTF-8') {
                $decode = utf8_decode ($decode) ;
            } 
            $this->headers['subject'] = str_replace ($match[0][$i], $decode, $this->headers['subject']) ;
        }
		// sanity anyone?
		if (!$this->headers['content-type']) { $this->headers['content-type'] = "text/plain; charset=us-ascii"; }
		if (!$simple) { $this->headers['content-type'] = $this->_ct_parse(); }
        

		return TRUE;
	}

	// _get_body: pulls the body out of the data and fills $this->body, decoding the data if nessesary.
	function _get_body($data,$simple = FALSE) {
		$lines = preg_split('/\n/', $data);
		$doneheaders = FALSE;
        
		$data = "";
		while (list($key,$val) = each($lines)) {
            //echo htmlspecialchars($val)."<br>";
			if (($val == '') and (!$doneheaders)) {
				$doneheaders = TRUE;
				continue;
			} else if ($doneheaders) {
				$data .= $val . "\n";
			}
		}

		// now here comes the fun part... decoding.
		switch($this->headers['content-transfer-encoding']) {
			case 'binary':
				$this->body = $this->_cte_8bit($this->_cte_qp($this->_cte_binary($data)),$simple);
				break;

			case 'base64':
				$this->body = $this->_cte_8bit($this->_cte_qp($this->_cte_base64($data)),$simple);
				break;

			case 'quoted-printable':
				$this->body = $this->_cte_8bit($this->_cte_qp($data),$simple);
				break;

			case '8bit':
				$this->body = $this->_cte_8bit($data,$simple);
				break;

			case '7bit':		// 7bit doesn't need to be decoded
			default:		// And the fall through as well...
				$this->body = $data;
				break;
		}
        //echo  $this->headers['content-type'][2][1];
        if (isset($this->headers['content-type'][2][1]) && $this->headers['content-type'][2][1] == 'UTF-8') {
                //$this->body = utf8_decode ($this->body) ;
                //echo quoted_printable_decode(utf8_decode ($this->body)) ;
        }
		if ($simple) { return TRUE; }

		// if we are a multipart message then break up the parts and decode, set the appropriate variables.
		// here comes the best part about making ezmlm-php OOP. since each part is just really a little message
		// in itself each part becomes a new parser object and all the wheels turn again... :)
		if ($this->multipart) {
            
			$boundary = '';
			for ($i = 2; $i <= count($this->headers['content-type']); $i++) {
				if (preg_match('/boundary/i', $this->headers['content-type'][$i][0])) {
					$boundary = $this->headers['content-type'][$i][1];
                    
				}
			}
			if ($boundary != '') {
				$this->_get_parts($this->body,$boundary);
			} else {
				// whoopps... something's not right here. we were told that the message is supposed
				// to be a multipart message, yet the boundary wasn't set in the content type.
				// mark the message as non multipart and add a message to the top of the body.
				$this->multipart = FALSE;
				$this->body = "PARSER ERROR:\nWHILE PARSING THIS MESSAGE AS A MULTIPART MESSAGE AS DEFINED IN RFC2045 THE BOUNDARY IDENTIFIER WAS NOT FOUND!\nTHIS MESSAGE WILL NOT DISPLAY CORRECTLY!\n\n" . $this->body;
			}
		}

		return TRUE;
	}

	// _get_parts: breaks up $data into parts based on $boundary following the rfc specs
	// detailed in section 5 of RFC2046 (http://www.faqs.org/rfcs/rfc2046.html)
	// After the parts are broken up they are then turned into parser objects and the
	// resulting array of parts is set to $this->parts;
	function _get_parts($data,$boundary) {
		$inpart = -1;
		$lines = preg_split('/\n/', $data);
        // La premi�re partie contient l'avertissement pour les client mail ne supportant pas
        // multipart, elle est stock� dans parts[-1]
		while(list($key,$val) = each($lines)) {
			if ($val == "--" . $boundary) { $inpart++; continue; } // start of a part
			else if ($val == "--" . $boundary . "--") { break; } // the end of the last part
			else { $parts[$inpart] .= $val . "\n"; }
		}
        
		for ($i = 0; $i < count($parts) - 1; $i++) {    // On saute la premi�re partie
			$part[$i] = new ezmlm_parser();
			$part[$i]->parse($parts[$i]);
			$this->parts[$i] = $part[$i];
            //echo $this->parts[$i]."<br>" ;
		}
        
	}	

	// _cte_8bit: decode a content transfer encoding of 8bit
	// NOTE: this function is a little bit special. Since the end result will be displayed in
	// a web browser _cte_8bit decodes ASCII characters > 127 (the US-ASCII table) into the
	// html ordinal equivilant, it also ensures that the messages content-type is changed
	// to include text/html if it changes anything...
	function _cte_8bit($data,$simple = FALSE) {
		if ($simple) { return $data; }
		$changed = FALSE;
		$chars = preg_split('//',$data);
		while (list($key,$val) = each($chars)) {
			if (ord($val) > 127) { $out .= '&#' . ord($val) . ';'; $changed = TRUE; }
			else { $out .= $val; }
		}
		if ($changed) { $this->headers['content-type'][1] = 'text/html'; }
		return $out;
	}

	// _cte_binary: decode a content transfer encoding of binary
	function _cte_binary($data) { return $data; }

	// _cte_base64: decode a content transfer encoding of base64
	function _cte_base64($data) { return base64_decode($data); }

	// _cte_qp: decode a content transfer encoding of quoted_printable
	function _cte_qp($data) {
		// For the time being we'll use PHP's function, it seems to work well enough.
		return quoted_printable_decode($data);
	}
    
}
