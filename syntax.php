<?php
/**
 * DokuWiki zoom pluin (Syntax component)
 *
 * Make images zoomable
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Heiko Höbel
 * @modified   Satoshi Sahara <sahara.satoshi@gmail.com>
 * Uses Cloud Zoom v1.0.2.x, Copyright (c) 2010 R Ceccor, www.professorcloud.com
 *
 *  SYNTAX:
 *      {{zoom WxH zoom_parameters > file }}
 *      {{zoom zoom_parameters > file?WxH }}
 *      {{zoom zoom_parameters > file?W }}
 *
 *      {{zoom> file?WxH&zoom_parameters }}  (original syntax, to be deprecated)
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

        $match = substr($match, 6, -2); //strip markup
        list($params, $media) = explode('>', $match, 2);
        list($media,  $title) = explode('|', $media, 2);
        $params = trim($params);

        // alignment
        $data['align'] = 0;
        if (substr($media, 0, 1) == ' ') {
            if (substr($media, -1, 1) == ' ') {
                $data['align'] = 3;  // cloud-zoom-center
            } else {
                //$data['align'] = 1;  // cloud-zoom-block-right
                $data['align'] = 4;  // cloud-zoom-float-right
            }
        } elseif (substr($media, -1, 1) == ' ') {
            //$data['align'] = 2;      // cloud-zoomblock-left
            $data['align'] = 5;      // cloud-zoom-float-left
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
        $media =trim($media);

        // check whether $media has zoom parameters, 旧シンタックスの救済措置
        if (empty($params) && preg_match('/\?(\d+)([xX](\d+))?&/', $media, $matches)) {
            list($media, $params) = explode('&', $media, 2);
        }
        // check whether $media has size parameters, eg. ?32x32
        if (preg_match('/\?(\d+)([xX](\d+))?/', $media, $matches)) {
            $media = str_replace($matches[0],'', $media);
            $params = substr($matches[0],1).' '.$params;
        }
        $img = trim($media);

        // determine image size, even if URL is given
        if (preg_match('#^(https?|ftp)://#i', $img)) {
            $data['image'] = $img;
            list($data['imageWidth'], $data['imageHeight']) = @getimagesize($img);
        } else {
            // properly handle relative names
            $data['image'] = resolve_id(getNS($ID),$img);
            list($data['imageWidth'], $data['imageHeight']) = @getimagesize(mediaFN($data['image']));
        }

        // separate size and zoom parameters
        if (preg_match('/^\d+([xX]\d+)?/', $params)){
            list($size_params, $zoom_params) = explode(' ', $params, 2);
        } else {
            $zoom_params = $param;
        }

        // size params
        if ($data['imageWidth'])  $data['width']  = $data['imageWidth'];
        if ($data['imageHeight']) $data['height'] = $data['imageHeight'];
        if (preg_match('/\b(\d+)[xX](\d+)\b/', $size_params, $match)){
            $data['width']  = $match[1];
            $data['height'] = $match[2];
            $size_params = str_replace($match[0],'', $size_params);
        } elseif (preg_match('/\b[xX](\d+)\b/', $size_params, $match)){ // 非推奨
            $data['height']  = $match[1];
            $data['width'] = round($match[1]*$data['imageWidth']/$data['imageHeight']);
            $size_params = str_replace($match[0],'', $size_params);
        } elseif (preg_match('/\b(\d+)\b/',$size_params, $match)){
            $data['width']  = $match[1];
            $data['height'] = round($match[1]*$data['imageHeight']/$data['imageWidth']);
            $size_params = str_replace($match[0],'', $size_params);
        }

        // zoom params for cloud-zoom, remove unwanted quotes and other chars
        $zoom_params = str_replace(chr(34), "", $zoom_params); // '"'
        $zoom_params = str_replace(chr(47), "", $zoom_params); // '/'
        $zoom_params = str_replace(chr(92), "", $zoom_params); // '\'
        if (!isset($zoom_params) || empty($zoom_params) || strlen($zoom_params) < 5) {
            $data['zoom_params'] = "position: 'inside', adjustX: -1, adjustY: -1, showTitle: false";
        } else {
            if (strpos($zoom_params,'position') === false){
                $data['zoom_params'] = "position:'inside', adjustX:-1, adjustY:-1, showTitle:false, ";
            } else {
                $data['zoom_params'] = "showTitle:false, ";
            }
            $data['zoom_params'].= trim($zoom_params);
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
                '" class="cloud-zoom" rel="' . $data['zoom_params'] .'">';
        $html.= '<img src="'.ml($data['image'], array('w'=>$data['width'],'h'=>$data['height'])).
                '" width="'.$data['width'].'" height="'.$data['height'].'" alt="" />';
        $html.= '</a></div></div>';

        $renderer->doc .= $html;
        return true;
    }

}

