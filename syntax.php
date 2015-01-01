<?php
/**
 * DokuWiki zoom pluin (Syntax component)
 *
 * Make images zoomable
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Heiko Höbel
 * @modified   Satoshi Sahara <sahara.satoshi@gmail.com>
 * Uses Cloud Zoom, Copyright (c) 2010 R Ceccor, www.professorcloud.com
 * and jQuery (jQuery.org)
   SYNTAX:
       {{zoom widthxheight parameters > file }}

       {{zoom>file?widthxheight&parameters}}   (original syntax)
 */

if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_zoom extends DokuWiki_Syntax_Plugin {

    public function getType() { return 'substition'; }
    public function getPType(){ return 'block'; }
    public function getSort() { return 301; }

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{zoom.*?\>.*?}}', $mode, 'plugin_zoom');
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){
        global $ID;

        $data = array( // set default
            'width'         => 500,
            'height'        => 250,
        );

        $match = substr($match, 6, -2); //strip markup from start and end
        list($all_params, $media) = explode('>', $match, 2);
        // take care original syntax
        if ($all_params == '') {
            list($media, $all_params) = explode('?', $media, 2);
        }

        // alignment
        $data['align'] = 0;
        if (substr($media, 0, 1) == ' ') {
            if (substr($media, -1, 1) == ' ') {
                $data['align'] = 3;  // cloud-zoom-center
            } else {
                $data['align'] = 1;  // cloud-zoom-block-right
            }
        } elseif (substr($media, -1, 1) == ' ') {
            $data['align'] = 2;      // cloud-zoomblock-left
        } elseif (substr($media, 0, 1) == '*') {
            $media = substr($media, 1);
            if (substr($media, -1, 1) == '*') {
                $media = substr($media, 0, -1);
                $data['align'] = 3;  // cloud-zoom-center
            } else {
                $data['align'] = 4;  // cloud-zoom-float-right
            }
        } elseif (substr($media, -1, 1) == '*') {
            $media = substr($media, 0, -1);
            $data['align'] = 5;      // cloud-zoom-float-left
        }
        $img =trim($media);

        // extract params, separeted by white space
        list($params, $ext_params) = explode("\s+", trim($all_params), 2);
        // remove unwanted quotes and other chars
        $ext_params = str_replace(chr(34), "", $ext_params);
        $ext_params = str_replace(chr(47), "", $ext_params);
        $ext_params = str_replace(chr(92), "", $ext_params);
        if (!isset($ext_params) || empty($ext_params) || strlen($ext_params) < 5) {
            $data['ext_params'] = "position: 'inside', adjustX: -1, adjustY: -1, showTitle: false";
        } else {
            if (strpos($ext_params,"position") === false){
                $data['ext_params'] = "position:'inside', adjustX:-1, adjustY:-1, showTitle:false, " . trim($ext_params);
            } else {
                $data['ext_params'] = "showTitle:false, " . trim($ext_params);
            }
        }

        // determine image size, even if URL is given
        if (preg_match('#^(https?|ftp)://#i', $img)) {
            $data['image'] = $img;
            list($data['imageWidth'], $data['imageHeight']) = @getimagesize($img);
        } else {
            // properly handle relative names
            $data['image'] = resolve_id(getNS($ID),$img);
            list($data['imageWidth'], $data['imageHeight']) = @getimagesize(mediaFN($data['image']));
        }

        // size
        if (preg_match('/\b(\d+)[xX](\d+)\b/', $params, $match)){
            $data['width']  = $match[1];
            $data['height'] = $match[2];
        } else {
            if (preg_match('/\b[xX](\d+)\b/', $params, $match)){
                $data['height']  = $match[1];
                $data['width'] = round($match[1]*$data['imageWidth']/$data['imageHeight']);
            } elseif (preg_match('/\b(\d+)\b/',$params,$match)){
                $data['width']  = $match[1];
                $data['height'] = round($match[1]*$data['imageHeight']/$data['imageWidth']);
            }
        }
        return $data;
    }

    /**
     * Create output
     */
    public function render($format, Doku_Renderer $renderer, $data) {
        if($format != 'xhtml') return false;

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
        $html = '<div style="'.$style.'" class="'.$align.'">';
        $html.= '<div style="position:relative;">';
        $html.= '<a href="'.ml($data['image'], array('w'=>$data['imageWidth'],'h'=>$data['imageHeight'])).
                '" class="cloud-zoom" rel="' . $data['ext_params'] .'">';
        $html.= '<img src="'.ml($data['image'], array('w'=>$data['width'],'h'=>$data['height'])).
                '" width="'.$data['width'].'" height="'.$data['height'].'" alt="" />';
        $html.= '</a></div></div>';

        $renderer->doc .= $html;
        return true;
    }

}

