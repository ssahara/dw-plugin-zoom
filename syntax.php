<?php
/**
 * Make images zoomable
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Heiko Höbel
 * Uses Cloud Zoom, Copyright (c) 2010 R Ceccor, www.professorcloud.com
 * and jQuery (jQuery.org)
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_zoom extends DokuWiki_Syntax_Plugin {

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }

    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort(){
        return 301;
    }


    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{zoom>[^}]*\}\}',$mode,'plugin_zoom');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        global $ID;

        $data = array(
            'width'         => 500,
            'height'        => 250,
        );

        $match = substr($match,7,-2); //strip markup from start and end

		// alignment
        $data['align'] = 0;
        if (substr($match,0,1) == ' ') {
			if (substr($match,-1,1) == ' ') {
				$data['align'] = 3;
			}
			else
			{
				$data['align'] = 1;
			}
		}
		elseif (substr($match,-1,1) == ' ') {
			$data['align'] = 2;
		}
		elseif (substr($match,0,1) == '*') {
			$match = substr($match,1);
			if (substr($match,-1,1) == '*') {
				$match = substr($match,0,-1);
				$data['align'] = 3;
			}
			else
			{
				$data['align'] = 4;
			}
		}
		elseif (substr($match,-1,1) == '*') {
				$match = substr($match,0,-1);
				$data['align'] = 5;
		}
		
        // extract params
        list($img,$all_params) = explode('?',$match,2);
		// extract params
		list($params,$ext_params) = explode('&',$all_params,2);
        $img = trim($img);
		//remove unwanted quotes and other chars
		$ext_params = str_replace(chr(34),"",$ext_params);
		$ext_params = str_replace(chr(47),"",$ext_params);
		$ext_params = str_replace(chr(92),"",$ext_params);
		if (!isset($ext_params) || empty($ext_params) || strlen($ext_params) < 5) {
			$data['ext_params'] = "position: 'inside', adjustX: -1, adjustY: -1, showTitle: false";
		} 
		else
		{
			if(strpos($ext_params,"position")=== false){
				$data['ext_params'] = "position:'inside', adjustX:-1, adjustY:-1, showTitle:false, " . trim($ext_params);
			}
			else
			{
				$data['ext_params'] = "showTitle:false, " . trim($ext_params);
			}
		}
        // resolving relatives
        $data['image'] = resolve_id(getNS($ID),$img);

        $file = mediaFN($data['image']);
        list($data['imageWidth'],$data['imageHeight']) = @getimagesize($file);

        // size
        if(preg_match('/\b(\d+)[xX](\d+)\b/',$params,$match)){
            $data['width']  = $match[1];
            $data['height'] = $match[2];
        }
		else
		{
			if(preg_match('/\b[xX](\d+)\b/',$params,$match)){
				$data['height']  = $match[1];
				$data['width'] = $match[1]*$data['imageWidth']/$data['imageHeight'];
			}	
			else if(preg_match('/\b(\d+)\b/',$params,$match)){
				$data['width']  = $match[1];
				$data['height'] = $match[1]*$data['imageHeight']/$data['imageWidth'];
			}
		}
        return $data;
    }

    /**
     * Create output
     */
    function render($mode, &$R, $data) {
        if($mode != 'xhtml') return false;
        global $ID;
		
		$align = '';
		switch ($data['align']) {
			case 1:
				$align = 'cloud-zoom-block-right';
				break;
			case 2:
				$align = 'cloud-zoomblock-left';
				break;
			case 3:
				$style = 'width:' . $data['width'] . 'px;';
				$align = 'cloud-zoom-center';
				break;
			case 4:
				$align = 'cloud-zoom-float-right';
				break;
			case 5:
				$align = 'cloud-zoom-float-left';
				break;				
		}
        $img = '<div style="' . $style . '" class="' . $align . '"><div style="position:relative;"><a href="'.ml($data['image'], array('w'=>$data['imageWidth'],'h'=>$data['imageHeight'])).'" class="cloud-zoom" rel="' . $data['ext_params'] .'"><img src="'.
                    ml($data['image'], array('w'=>$data['width'],'h'=>$data['height'])).'" width="'.
                    $data['width'].'" height="'.$data['height'].'" alt="" /></a></div></div>';

        $R->doc .= $img;
        return true;
    }

}

