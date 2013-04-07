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
class custom_fields_task_Core {
  static function available_tasks() {
    // Delete extra search_records
    db::build()
      ->delete("custom_fields_records")
      ->where("item_id", "NOT IN", db::build()->select("id")->from("items"))
      ->execute();

    list ($remaining, $total, $percent) = custom_fields::stats();
    return array(Task_Definition::factory()
                 ->callback("custom_fields_task::update_index")
                 ->name(t("Update Custom Fields Index"))
                 ->description(
                   $remaining
                   ? t2("1 photo or album needs to be scanned",
                        "%count (%percent%) of your photos and albums need to be scanned",
                        $remaining, array("percent" => (100 - $percent)))
                   : t("Search data is up-to-date"))
                 ->severity($remaining ? log::WARNING : log::SUCCESS));
  }

  static function update_index($task) {
    try {
      $completed = $task->get("completed", 0);

      $start = microtime(true);
      foreach (ORM::factory("item")
               ->join("custom_fields_records", "items.id", "custom_fields_records.item_id", "left")
               ->where("custom_fields_records.item_id", "IS", null)
               ->or_where("custom_fields_records.dirty", "=", 1)
               ->find_all(100) as $item) {
        // The query above can take a long time, so start the timer after its done
        // to give ourselves a little time to actually process rows.
        if (!isset($start)) {
          $start = microtime(true);
        }

        custom_fields::update($item);
        $completed++;

        if (microtime(true) - $start > .75) {
          break;
        }
      }

      list ($remaining, $total, $percent) = custom_fields::stats();
      $task->set("completed", $completed);
      if ($remaining == 0 || !($remaining + $completed)) {
        $task->done = true;
        $task->state = "success";
        site_status::clear("custom_fields_index_out_of_date");
        $task->percent_complete = 100;
      } else {
        $task->percent_complete = round(100 * $completed / ($remaining + $completed));
      }
      $task->status = t2("one record updated, index is %percent% up-to-date",
                         "%count records updated, index is %percent% up-to-date",
                         $completed, array("percent" => $percent));
    } catch (Exception $e) {
      $task->done = true;
      $task->state = "error";
      $task->status = $e->getMessage();
    }
  }
}
