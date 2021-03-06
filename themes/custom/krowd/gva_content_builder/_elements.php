<?php
if(!function_exists('scrape_insta_hash')){
    function scrape_insta_hash($tag) {
       $insta_source = file_get_contents('https://www.instagram.com/'.trim($tag)); // instagrame tag url
       $shards = explode('window._sharedData = ', $insta_source);
       $insta_json = explode(';</script>', $shards[1]); 
       $insta_array = json_decode($insta_json[0], TRUE);
       return $insta_array; // this return a lot things print it and see what else you need
    }
}

function gavias_content_builder_set_elements(){
   return $shortcodes = array(
    'gva_column',
    'gva_row',
    'gva_accordion',
    'gva_box_color', 
    'gva_box_icon',
    'gva_call_to_action',
    'gva_chart',
    'gva_text',
    'gva_text_noeditor',
    'gva_counter',
    'gva_drupal_block',
    'gva_heading',
    'gva_icon_box_classic',
    'gva_icon_box_style',
    'gva_image',
    'gva_our_team',
    'gva_pricing_item',
    'gva_progress',
    'gva_tabs',
    'gva_video_box',
    'gva_gmap',
    'gva_button',
    'gva_view',
    'gva_quote_text',
    'gva_image_content',
    'gva_gallery',
    'gva_our_partners',
    'gva_download',
    'gva_socials',
    'gva_carousel_content',
    'gva_progress_work',
    'gva_job_box',
    'gva_icon_list'
  );
}

function gavias_merge_atts( $pairs, $atts, $shortcode = '' ) {
    $atts = (array)$atts;
    $out = array();
    foreach($pairs as $name => $default) {
        if ( array_key_exists($name, $atts) )
            $out[$name] = $atts[$name];
        else
            $out[$name] = $default;
    }
    return $out;
}

function gavias_implode_classes(array $classes){
    if(isset($classes) && count($classes) > 0 ){
        return(' ' . implode(' ', $classes));
    } 
}

function gavias_render_css($screen, $class_css, $array_css){
    $result = $tmp_css = '';
    
    if($screen == 'xl'){
        if(is_array($array_css)){
            $tmp_css = count($array_css) ? implode('; ', $array_css) : '';
        }else{
            $tmp_css = $array_css;
        }
        $result .= !empty($tmp_css) ? $class_css . '{' . $tmp_css . '}' . PHP_EOL : '';
    }

    if($screen == 'lg'){
        if(is_array($array_css)){
            $tmp_css = count($array_css) ? implode('; ', $array_css) : '';
        }else{
            $tmp_css = $array_css;
        }
        $result .= !empty($tmp_css) ? '@media(max-width: 1199.98px){' . $class_css . '{' . $tmp_css . '}' . '}' . PHP_EOL : '';
    }

    if($screen == 'md'){
        if(is_array($array_css)){
                $tmp_css = count($array_css) ? implode('; ', $array_css) : '';
        }else{
            $tmp_css = $array_css;
        }
        $result .= !empty($tmp_css) ? '@media(max-width: 991.98px){' . $class_css . '{' . $tmp_css . '}' . '}' . PHP_EOL : '';
    }

    return $result;
}