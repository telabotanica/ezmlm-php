<?php
// $Id: ezmlm.php,v 1.5 2007/04/19 15:34:35 neiluj Exp $
//
// ezmlm.php - ezmlm-php v2.0
// --------------------------------------------------------------
// As the site that ezmlm-php was developed for grew, and grew
// the old system used had to be bandaid fixed more, and more
// because the site started moving to an object oriented design
// for all the backend systems and ezmlm wasn't playing nice
// with the new design. So, ezmlm was redesigned too, and here
// it is.
//
// It may look a little more confusing if you're not used to
// working with objects but it actually is much more effiecient
// and organized in it's new incarnation.
// Simply edit the variables in the ezmlm-php constructor below
// just like you would with the old ezmlm-php-config.php file,
// if you're unsure howto do this check out the file CONFIG,
// then check the USAGE file for how you should include and use
// the new classes if you are integrating ezmlm-php into your
// site.
// (SEARCH FOR: USER-CONFIG to find where to edit.)
// --------------------------------------------------------------

require_once("ezmlm-errors.def");
require_once("ezmlm-parser.php");
require_once("ezmlm-threads.php");
require_once("ezmlm-listinfo.php");
require_once("ezmlm-msgdisplay.php");
require_once("ezmlm-repondre.php");
require_once("ezmlm-author.php");

$GLOBALS['mois'] = array ('Jan', 'F�v', 'Mars', 'Avril', 'Mai', 'Juin', 'Juil', 'Ao�t', 'Sept', 'Oct', 'Nov', 'D�c') ;

// CLASS: ezmlm_php
// the base class, contains common functions and the config
class ezmlm_php {
	var $listdir;		// the root directory of the list
	var $listname;		// the list address upto the @
	var $listdomain;	// the domain for the list

	var $tempdir;		// a directory in which the webserver can write cache files

	var $sendheaders;	// send generic page headers
	var $sendbody;		// send generic body definitions
	var $sendfooters;	// send generic page footers
	var $includebefore;	// a file to include before the content
	var $includeafter;	// a file to include after the content

	var $href;		// what to add before the '?param=value' in links

	var $prefertype;	// what mime type do you prefer?
	var $showheaders;	// what headers should we show?

	var $msgtemplate;	// the template for displaying messages (see the file TEMPLATE)

	var $tablecolours;	// what are the colours for the table rows?

	var $thread_subjlen;	// the maximum length of subjects in the thread view (0 = no limit)

	var $forcehref;		// force the base of makelink();

	// --------- END USER CONFIGURATION ---------

	// Internal variables
	var $action = '';
	var $actionargs;

	function ezmlm_php() {
		
		// USER-CONFIG section
		// these variables act the same way ezmlm-php-config.php did in the first release
		// simply edit these variables to match your setup
                                
		$this->listdir		= "";
		$this->listname		= "";
		$this->listdomain	= "";
	
		$this->tempdir		= "";

		$this->sendheaders	= TRUE;
		$this->sendbody		= TRUE;
		$this->sendfooters	= TRUE;
		$this->includebefore	= "";
		$this->includeafter	= "";

		$this->href		= "";

		$this->prefertype	= "text/html";
		$this->showheaders	= array(
						"to",
						"from",
						"subject",
						"date"
					);
        $this->header_en_francais = array ('to' => 'A', 
                                            'from' => 'De',
                                            'subject' => 'Sujet',
                                            'date' => 'Date') ;

		$this->msgtemplate	= "<pre><ezmlm-body></pre>"; // if blank it will use the internal one

		$this->tablecolours	= array(
						// delete the next line if you don't want alternating colours
						"#eeeeee",
						"#ffffff"
					);

		$this->thread_subjlen	= 55;

		// --- STOP EDITING HERE ---
		// some sanity checking
		if ((!is_dir($this->listdir . "/archive")) or
		    (!is_dir($this->listdir . "/archive/authors")) or
		    (!is_dir($this->listdir . "/archive/threads")) or
		    (!is_dir($this->listdir . "/archive/subjects"))) {
            return false ;
			/*$this->error(EZMLM_INVALID_DIR,TRUE);*/
		}
	}

	function set_action($action) {
		if (is_array($action)) { $this->error(EZMLM_INVALID_SYNTAX,TRUE); }
		$this->action = $action;
	}
	function set_actionargs($actionargs) {
		if ($this->action == '') { $this->error(EZMLM_INVALID_SYNTAX,TRUE); }
		$this->actionargs = $actionargs;
	}

	function run() {
		if ($this->action == '') { $this->error(EZMLM_INVALID_SYNTAX,TRUE); }

		if ($this->sendheaders) { $this->sendheaders(); }
		if ($this->sendbody) { $this->sendbody(); }
		if ($this->includebefore != '') { @include_once($this->includebefore); }

		switch ($this->action) {
			case "list_info":
				$info = new ezmlm_listinfo();
				$info->display();
				break;
			case "show_msg":
				if (count($this->actionargs) < 2) {
					$this->error(EZMLM_INVALID_SYNTAX,TRUE);
				}
				$show_msg = new ezmlm_msgdisplay();
				$show_msg->display($this->actionargs[0] . "/" . $this->actionargs[1]);
				break;
			case "show_threads":
				$threads = new ezmlm_threads();
				$threads->load($this->actionargs[0]);
				break;
			case "show_author_msgs":
				$author = new ezmlm_author();
				$author->display($this->actionargs[0]);
				break;
		}

		if ($this->includeafter != '') { @include_once($this->includeafter); }
		if ($this->sendfooters) { $this->sendfooters(); }
	}

	function sendheaders() {
		print "<html><head>\n";
		print "<style type=\"text/css\">\n";
		print "<!--\n";
		print ".heading { font-family: helvetica; font-size: 16px; line-height: 18px; font-weight: bold; }\n";
		print "//-->\n";
		print "</style>\n";
		print "</head>\n";
	}

	function sendbody() {
		print "<body>\n";
	}

	function sendfooters() {
		print "</body>\n";
		print "</html>\n";
	}
				

	// begin common functions

	// makehash - generates an author hash using the included makehash program
	function makehash($str) {
         $str = preg_replace ('/>/', '', $str) ;
        $handle = popen ('/usr/local/lib/safe_mode/makehash \''.$str.'\'', 'r') ;
        $hash = fread ($handle, 256) ;
        pclose ($handle) ;
		return $hash;
	}

	// makelink - writes the <a href=".."> tag
	function makelink($params,$text) {
		if ($this->forcehref != "") {
			$basehref = $this->forcehref;
		} else {
			$basehref = preg_replace('/^(.*)\?.*/', '\\1', $_SERVER['REQUEST_URI']);
		}
		$link = '<a href="'. $basehref . '&amp;' . $params . '">' . $text . '</a>';
		return $link;
	}

	// md5_of_file - provides wrapper function that emulates md5_file for PHP < 4.2.0
	function md5_of_file($file) {
		if (function_exists("md5_file")) { // php >= 4.2.0
			return md5_file($file);
		} else {
			if (is_file($file)) {
				$fd = fopen($file, "rb");
				$filecontents = fread($fd, filesize($file));
				fclose ($fd);
				return md5($filecontents);
			} else {
				return FALSE;
			}
		}
	}

	// protect_email - protects email address turns user@domain.com into user@d...
	function protect_email($str,$short = FALSE) {
		if (preg_match("/[a-zA-Z0-9\-\.]\@[a-zA-Z0-9\-\.]*\./", $str)) {
			$outstr = preg_replace("/([a-zA-Z0-9\-\.]*\@)([a-zA-Z0-9\-\.])[a-zA-Z0-9\-\.]*\.[a-zA-Z0-9\-\.]*/","\\1\\2...",$str);
			$outstr = preg_replace("/\</", '&lt;', $outstr);
			$outstr = preg_replace("/\>/", '&gt;', $outstr);
		} else {
			$outstr = $str;
		}

		if ($short) {
			$outstr = preg_replace("/&lt;.*&gt;/", '', $outstr);
			$outstr = preg_replace("/[\"']/", '', $outstr);
		}
		return trim($outstr);
	}

	// cleanup_body: sortta like protect_email, just for message bodies
	function cleanup_body($str) {
			$outstr = preg_replace("/([a-zA-Z0-9\-\.]*\@)([a-zA-Z0-9\-\.])[a-zA-Z0-9\-\.]*\.[a-zA-Z0-9\-\.]*/","\\1\\2...",$str);
			return $outstr;
	}

	function error($def, $critical = FALSE) {
		global $ezmlm_error;

		print "\n\n";
		print "<table width=600 border=1 cellpadding=3 cellspacing=0>\n";
		print "<tr bgcolor=\"#cccccc\"><td><b>EZMLM-PHP Error: " . $ezmlm_error[$def]['title'] . "</td></tr>\n";
		print "<tr bgcolor=\"#aaaaaa\"><td>" . $ezmlm_error[$def]['body'] . "</td></tr>\n";
		print "</table>\n\n";

		if ($critical) { die; }
	}
    /**
     *  Parse une chaime et supprime les probl�me d'encodage de type ISO-4 ...
     *
     * @return string
     */
    
    function decode_iso ($chaine) {
        
        if (preg_match ('/windows-[0-9][0-9][0-9][0-9]/i', $chaine, $nombre)) {
            $reg_exp = $nombre[0] ;
            $chaine = str_replace(' ', '', $chaine);
        } else {
            $reg_exp = 'ISO-8859-15?' ;
        }
        if (preg_match ('/UTF/i', $chaine)) $reg_exp = 'UTF-8' ;
        preg_match_all ("/=\?$reg_exp\?(Q|B)\?(.*?)\?=/i", $chaine, $match, PREG_PATTERN_ORDER)  ;
        for ($i = 0; $i < count ($match[0]); $i++ ) {
            
                if (strtoupper($match[1][$i]) == 'Q') {
                    $decode = quoted_printable_decode ($match[2][$i]) ;
                } elseif ($match[1][$i] == 'B') {
                    $decode = base64_decode ($match[2][$i]) ;
                }
                $decode = preg_replace ("/_/", " ", $decode) ;
            if ($reg_exp == 'UTF-8') {
                $decode = utf8_decode ($decode) ;
            } 
            $chaine = str_replace ($match[0][$i], $decode, $chaine) ;
        }
        return $chaine ;
    }
    
    /**
     *
     *
     * @return
     */
    
    function date_francaise ($date_mail) {
        $date_mail = preg_replace ('/\(?CEST\)?/', '', $date_mail) ;
        $numero_mois = date('m ', strtotime($date_mail)) - 1 ;
        $date = date ('d ', strtotime($date_mail)).$GLOBALS['mois'][$numero_mois] ; // Le jour et le mois
        $date .= date(' Y ', strtotime($date_mail)) ; // l'ann�e
        if (date('a', strtotime($date_mail)) == 'pm') {
            $date .= (int) date('g', strtotime($date_mail)) + 12 ;  // Les heures
        } else {
            $date .= date('g', strtotime($date_mail)) ;
        }
        $date .= date(':i', strtotime($date_mail)) ;    // Les minutes
        return $date ;
    }
    
    /** 
     * Cette fonction renvoie le prefixe, cad 0 ou rien
     * d un nom de message, ex : pour 09, on renvoie 0
     * pour 12 on renvoie rien
     */
    function prefixe_nom_message($nom) {
    	if (preg_match ('/0([1-9][0-9]*)/', $nom, $match)) {
			$nom_fichier = $match[1];
			return '0' ;
		} else {
			return '' ;
		}
    }
}

//
// --- END OF CLASS DEFINITION ---
//

// FIN
