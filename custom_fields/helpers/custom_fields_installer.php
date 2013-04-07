<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Custom Fields module
 * Copyright (C) 2011 Jozsef Rekedt-Nagy (jozsef.rnagy@site.hu)
 */
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2011 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class custom_fields_installer {
  static function install() {
    $db = Database::instance();
    // using IF NOT EXISTS, as tables does not get dropped ever
    $db->query("CREATE TABLE IF NOT EXISTS {custom_fields_freetext_map} (
                `item_id` int(9) NOT NULL,
                `property_id` int(6) NOT NULL,
                `value` varchar(200) NOT NULL,
                PRIMARY KEY (`item_id`,`property_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

    $db->query("CREATE TABLE IF NOT EXISTS {custom_fields_freetext_multilang} (
                `property_id` int(8) unsigned NOT NULL,
                `item_id` int(11) unsigned NOT NULL,
                `locale` varchar(8) NOT NULL,
                `value` varchar(200) NOT NULL,
                PRIMARY KEY (`property_id`,`item_id`,`locale`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

    $db->query("CREATE TABLE IF NOT EXISTS {custom_fields_properties} (
                `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
                `type` enum('freetext','dropdown','radio','checkbox','month','integer') NOT NULL,
                `name` varchar(40) NOT NULL,
                `context` enum('album','photo','both') NOT NULL COMMENT 'whether it belongs to albums, photos or both',
                `searchable` tinyint(1) unsigned NOT NULL,
                `thumb_view` tinyint(1) unsigned NOT NULL,
                `create_input` tinyint(1) unsigned NOT NULL COMMENT 'whether to show at album creation/photo upload time',
                `max_length` int(8) unsigned DEFAULT NULL COMMENT 'max length that shall ever be allowed to be storen in here, applicable to freetext only',
                `order` int(8) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

    $db->query("CREATE TABLE IF NOT EXISTS {custom_fields_properties_multilang} (
                `property_id` int(6) unsigned NOT NULL,
                `locale` varchar(8) NOT NULL,
                `name` varchar(40) NOT NULL,
                PRIMARY KEY (`property_id`,`locale`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

    $db->query("CREATE TABLE IF NOT EXISTS {custom_fields_selection} (
                `property_id` int(8) NOT NULL COMMENT 'links to custom_fields_properties.id',
                `selection_id` int(11) NOT NULL AUTO_INCREMENT,
                `value` varchar(100) NOT NULL,
                `order` int(8) unsigned NOT NULL DEFAULT '0',
                UNIQUE KEY `NoDupeOptions` (`property_id`,`selection_id`),
                KEY `id` (`property_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

    $db->query("CREATE TABLE IF NOT EXISTS {custom_fields_selection_multilang} (
                `property_id` int(8) unsigned NOT NULL,
                `selection_id` int(11) unsigned NOT NULL,
                `locale` varchar(8) NOT NULL,
                `value` varchar(100) NOT NULL,
                PRIMARY KEY (`property_id`,`selection_id`,`locale`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

    $db->query("CREATE TABLE IF NOT EXISTS {custom_fields_records} (
                `id` int(9) NOT NULL AUTO_INCREMENT,
                `item_id` int(9) DEFAULT NULL,
                `dirty` tinyint(1) DEFAULT '1',
                `data` longtext,
                PRIMARY KEY (`id`),
                KEY `item_id` (`item_id`),
                FULLTEXT KEY `data` (`data`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;");

    $db->query("CREATE TABLE IF NOT EXISTS {custom_fields_selection_map} (
                `item_id` int(9) NOT NULL,
                `property_id` int(6) NOT NULL,
                `selection_id` int(6) NOT NULL,
                PRIMARY KEY (`item_id`,`property_id`,`selection_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1;");

    module::set_version("custom_fields", 8);
  }


  static function upgrade($version) {
    $db = Database::instance();
    if ($version < 8) {
      Cache::instance()->delete_all();
      $db->query("ALTER TABLE {custom_fields_selection} ADD COLUMN `order` int(8) unsigned NOT NULL DEFAULT '0'");
      module::set_version("custom_fields", $version = 8);
    }
  }

  static function activate() {
    // Update the root item.  This is a quick hack because the search module is activated as part
    // of the official install, so this way we don't start off with a "your index is out of date"
    // banner.
    //custom_fields::update(model_cache::get("item", 1));
    custom_fields::check_index();
  }

  static function deactivate() {
    site_status::clear("custom_fields_index_out_of_date");
  }

  static function uninstall() {
    // As a safety measure against massive accidental data loss, not dropping or flushing our tables on module uninstall, 
    // but setting all items as dirty, so they will be updated next time for sure
    // Revisit: would be nice to flush non-existing items on reActivation
    Database::instance()->query("UPDATE {custom_fields_records} SET dirty = 1");
    //Database::instance()->query("DROP TABLE {custom_fields_records}");
  }
}
