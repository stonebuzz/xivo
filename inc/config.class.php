<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginXivoConfig extends Config {

   static function getTypeName($nb=0) {
      return __('Xivo', 'xivo');
   }

   /**
    * Return the current config of the plugin store in the glpi config table
    * @return array config with keys => values
    */
   static function getConfig() {
      return Config::getConfigurationValues('plugin:xivo');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      switch ($item->getType()) {
         case "Config":
            return self::createTabEntry(self::getTypeName());
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item,
                                            $tabnum=1,
                                            $withtemplate=0) {
      switch ($item->getType()) {
         case "Config":
            return self::showForConfig($item, $withtemplate);
      }

      return true;
   }

   static function showForConfig(Config $config,
                                     $withtemplate=0) {
      if (!self::canView()) {
         return false;
      }
      $current_config = self::getConfig();
      $canedit        = Session::haveRight(self::$rightname, UPDATE);
      echo "<div class='xivo_config'>";
      if ($canedit) {
         echo "<form name='form' action='".Toolbox::getItemTypeFormURL("Config")."' method='post'>";
      }
      echo "<div class='center' id='tabsbody'>";

      echo "<h1>".__("Configuration of XIVO connection")."</h1>";

      echo self::showField([
         'label'       => __("API url", 'xivo'),
         'name'        => 'api_url',
         'value'       => $current_config['api_url'],
         'placeholder' => 'https://...',
      ]);

      echo self::showField([
         'label'       => __("API username", 'xivo'),
         'name'        => 'api_username',
         'value'       => $current_config['api_username'],
         'style'       => 'width:100px;',
      ]);

      echo self::showField([
         'inputtype'   => 'password',
         'label'       => __("API password", 'xivo'),
         'name'        => 'api_password',
         'value'       => $current_config['api_password'],
         'style'       => 'width:100px;',
      ]);

      echo self::showField([
         'inputtype'   => 'yesno',
         'label'       => __("API check SSL", 'xivo'),
         'name'        => 'api_ssl_check',
         'value'       => $current_config['api_ssl_check'],
      ]);

      if ($canedit) {
         echo Html::hidden('config_class', ['value' => __CLASS__]);
         echo Html::hidden('config_context', ['value' => 'plugin:xivo']);
         echo Html::submit(_sx('button','Save'), [
            'name' => 'update'
         ]);
      }

      echo "</div>"; // #tabsbody
      Html::closeForm();

      if (self::isValid()) {
         echo "<h1>".__("API XIVO status")."</h1>";
         $apiclient = new PluginXivoAPIClient;
         $data_connect = $apiclient->connect();

         if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) {

            // display token
            if (isset($data_connect['data']['token'])) {
               echo "<h2>".__("Auth yoken", 'xivo')."</h2>";
               echo $data_connect['data']['token'];
            }

            // display acl
            if (isset($data_connect['data']['acls'])) {
               echo "<h2>".__("ACL", 'xivo')." (".count($data_connect['data']['acls']).")</h2>";
               echo "<ul>";
               foreach($data_connect['data']['acls'] as $right) {
                  echo "<li>$right</li>";
               }
               echo "</ul>";
            }

            $data_devices = $apiclient->getDevices();
            Html::printCleanArray($data_devices);
         }
      }
      echo "</div>"; // .xivo_config
   }

   static function showField($options = []) {
      $rand            = mt_rand();
      $default_options = [
         'inputtype'   => 'input',
         'label'       => '',
         'name'        => '',
         'value'       => '',
         'placeholder' => '',
         'style'       => 'width:90%;',
         'id'          => "xivoconfig_field_$rand",
         'class'       => "xivo_input",
         'required'    => "required",
      ];
      $options         = array_merge($default_options, $options);

      $out = "";
      $out.= "<div class='xivo_field'>";


      // display an hidden field to prevent chrome autofill

      // call the field according to its type
      switch($options['inputtype']) {
         default:
         case 'input':
            $out.= Html::input('fakefield', ['style' => 'display:none;']);
            $out.= Html::input($options['name'], $options);
            break;

         case 'password':
            $out.=  "<input type= password' name='fakefield' style='display:none;'>";
            $out.=  "<input type= password'";
            foreach($options as $key => $value) {
               $out.= "$key='$value' ";
            }
            $out.= ">";
            break;
         case 'yesno':
            $options['display'] = false;
            $out.= Dropdown::showYesNo($options['name'], $options['value'], -1, $options);
            break;
      }

      $out.= "<label class='xivo_label' for='{$options['id']}'>{$options['label']}</label>";
      $out.= "</div>";

      return $out;
   }

   static function isValid() {
      $current_config = self::getConfig();
      return  (!empty($current_config['api_url'])
               && !empty($current_config['api_username'])
               && !empty($current_config['api_password']));
   }

   /**
    * Database table installation for the item type
    *
    * @param Migration $migration
    * @return boolean True on success
    */
   static function install(Migration $migration) {
      global $CFG_GLPI;

      $current_config = self::getConfig();

      // fill config table with default values if missing
      foreach ([
         // api access
         'api_url'       => '',
         'api_username'  => '',
         'api_password'  => '',
         'api_ssl_check' => 1,
      ] as $key => $value) {
         if (!isset($current_config[$key])) {
            Config::setConfigurationValues('plugin:xivo', array($key => $value));
         }
      }
   }

   /**
    * Database table uninstallation for the item type
    *
    * @return boolean True on success
    */
   static function uninstall() {
      global $DB;

      $config = new Config();
      $config->deleteByCriteria(['context' => 'xivo']);

      return true;
   }
}