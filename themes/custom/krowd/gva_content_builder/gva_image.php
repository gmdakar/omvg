<?php 
if(!class_exists('element_gva_image')):
   class element_gva_image{
      public function render_form(){
         $fields =array(
            'type' => 'gsc_image',
            'title' => ('Image'), 
            'fields' => array(
               array(
                  'id'        => 'image',
                  'type'      => 'upload',
                  'title'     => t('Image'),
               ),
               array(
                  'id'        => 'align',
                  'type'      => 'select',
                  'title'     => t('Align Image'),
                  'options'   => array( 
                     ''          => 'None', 
                     'left'      => 'Left', 
                     'right'     => 'Right', 
                     'center'    => 'Center', 
                  ),
               ),
               array(
                  'id'     => 'alt',
                  'type'   => 'text',
                  'title'  => t('Alternate Text'),
               ),
               array(
                  'id'     => 'link',
                  'type'   => 'text',
                  'title'  => t('Link')
               ),
               array(
                  'id'        => 'target',
                  'type'      => 'select',
                  'options'   => array( 'off' => 'No', 'on' => 'Yes' ),
                  'title'  => t('Open in new window'),
                  'desc'      => t('Adds a target="_blank" attribute to the link.'),
               ),
               array(
                  'id'     => 'animate',
                  'type'      => 'select',
                  'title'  => t('Animation'),
                  'sub_desc'  => t('Entrance animation'),
                  'options'   => gavias_content_builder_animate(),
                  'class'     => 'width-1-2'
               ),
               array(
                  'id'        => 'animate_delay',
                  'type'      => 'select',
                  'title'     => t('Animation Delay'),
                  'options'   => gavias_content_builder_delay_aos(),
                  'desc'      => '0 = default',
                  'class'     => 'width-1-2'
               ), 
               array(
                  'id'        => 'el_class',
                  'type'      => 'text',
                  'title'     => t('Extra class name'),
                  'desc'      => t('Style particular content element differently - add a class name and refer to it in custom CSS.'),
               ),
            ),                                       
         );
         return $fields;
      }

      public static function render_content( $attr = array(), $content = '' ){
         global $base_url;
         extract(gavias_merge_atts(array(
            'image'           => '',
            'border'          => 'off',
            'alt'             => '',
            'align'           => 'none',
            'link'            => '',
            'target'          => 'off',
            'animate'         => '',
            'animate_delay'   => '',
            'el_class'        => ''
         ), $attr));
            
         $image = $base_url . $image; 

         if( $align ) $align = 'text-'. $align;
         
         if( $target=='on' ){
            $target = 'target="_blank"';
         } else {
            $target = '';
         }

         $class_array = array();
         $class_array[] = $align;
         $class_array[] = $el_class;
         if($animate) $class_array[] = ' wow ' . $animate;
         $classes = count($class_array) > 0 ? ' ' . implode(' ', $class_array) : '';
          ob_start();
         ?>
            <div class="widget gsc-image<?php print $classes ?>" <?php print gavias_content_builder_print_animate_wow('', $animate_delay) ?>>
               <div class="widget-content">
                  <?php if($link){ ?>
                     <a href="<?php print $link ?>" <?php print $target ?>>
                  <?php } ?> 
                    <img src="<?php print $image ?>" alt="<?php print $alt ?>" />
                  <?php if($link){print '</a>'; } ?>
               </div>
            </div>    
         <?php return ob_get_clean() ?>  
         <?php       
      }

   }
endif;   



