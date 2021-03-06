<?php

/**
 * @package World of Warcraft Armory
 * @version Release Candidate 1
 * @revision 415
 * @copyright (c) 2009-2010 Shadez
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 **/

Class SearchMgr {
    
    public $armory = null;
    private $searchQuery = false;
    public $get_array;
    public $heirloom = false;
    public $itemSearchSkip = false;
    
    public function SearchMgr($armory) {
        if(!is_object($armory)) {
            die('<b>Fatal Error:</b> armory must be instance of Armory class!');
        }
        $this->armory = $armory;
    }
    
    public function SetSearchQuery($searchQuery) {
        $this->searchQuery = $searchQuery;
    }
    
    public function GetSearchQuery() {
        return $this->searchQuery;
    }
    
    public function PerformItemsSearch($count = false, $findUpgrade = false, $player_level = 80) {
        if($this->itemSearchSkip == true) {
            return false;
        }
        if(!$this->searchQuery && !$findUpgrade && !$this->heirloom) {
            $this->armory->Log()->writeError('%s : unable to start search: no data provided', __METHOD__);
            return false;
        }
        if($findUpgrade > 0) {
            $source_item_data = $this->armory->wDB->selectRow("SELECT `class`, `subclass`, `InventoryType`, `ItemLevel`, `Quality`, `bonding` FROM `item_template` WHERE `entry`=%d", $findUpgrade);
            if(!$source_item_data) {
                $this->armory->Log()->writeError('%s : unable to item info for ID #%d (findUpgrade)', __METHOD__, $findUpgrade);
                return false;
            }
        }
        if($count == true) {
            if($findUpgrade) {
                $count_items = $this->armory->wDB->selectCell("SELECT COUNT(`entry`) FROM `item_template` WHERE `class`=%d AND `subclass`=%d AND `InventoryType`=%d AND `Quality` >= %d AND `ItemLevel` >= %d AND `RequiredLevel` <= %d", $source_item_data['class'], $source_item_data['subclass'], $source_item_data['InventoryType'], $source_item_data['Quality'], $source_item_data['ItemLevel'], $player_level);
            }
            elseif($this->heirloom == true) {
                $count_items = $this->armory->wDB->selectCell("SELECT COUNT(`entry`) FROM `item_template` WHERE `Quality`=7");
            }
            else {
                if($this->armory->GetLoc() == 0) {
                    $count_items = $this->armory->wDB->selectCell("SELECT COUNT(`entry`) FROM `item_template` WHERE `name` LIKE '%s'", '%'.$this->searchQuery.'%');
                }
                else {
                    $count_items = $this->armory->wDB->selectCell("SELECT COUNT(`entry`) FROM `item_template` WHERE `name` LIKE '%s' OR `entry` IN (SELECT `entry` FROM `locales_item` WHERE `name_loc%d` LIKE '%s')", '%'.$this->searchQuery.'%', $this->armory->GetLoc(), '%'.$this->searchQuery.'%');
                }
            }
            if($count_items > 200) {
                return 200;
            }
            return $count_items;
        }
        if($findUpgrade) {
            $items = $this->armory->wDB->select("SELECT `entry` AS `id`, `name`, `ItemLevel`, `Quality` AS `rarity`, `displayid`, `bonding`, `flags`, `duration` FROM `item_template` WHERE `class`=%d AND `subclass`=%d AND `InventoryType`=%d AND `Quality` >= %d AND `ItemLevel` >= %d AND `RequiredLevel` <= %d ORDER BY `ItemLevel` DESC LIMIT 200", $source_item_data['class'], $source_item_data['subclass'], $source_item_data['InventoryType'], $source_item_data['Quality'], $source_item_data['ItemLevel'], $player_level);
        }
        elseif($this->heirloom == true) {
            $items = $this->armory->wDB->select("SELECT `entry` AS `id`, `name`, `ItemLevel`, `Quality` AS `rarity`, `displayid`, `bonding`, `flags`, `duration` FROM `item_template` WHERE `Quality`=7 ORDER BY `ItemLevel` DESC LIMIT 200");
        }
        else {
            if($this->armory->GetLoc() == 0) {
                $items = $this->armory->wDB->select("SELECT `entry` AS `id`, `name`, `ItemLevel`, `Quality` AS `rarity`, `displayid`, `bonding`, `flags`, `duration` FROM `item_template` WHERE `name` LIKE '%s' ORDER BY `ItemLevel` DESC LIMIT 200", '%'.$this->searchQuery.'%');
            }
            else {
                $items = $this->armory->wDB->select("SELECT `entry` AS `id`, `name`, `ItemLevel`, `Quality` AS `rarity`, `displayid`, `bonding`, `flags`, `duration` FROM `item_template` WHERE `name` LIKE '%s' OR `entry` IN (SELECT `entry` FROM `locales_item` WHERE `name_loc%d` LIKE '%s') ORDER BY `ItemLevel` DESC LIMIT 200", '%'.$this->searchQuery.'%', $this->armory->GetLoc(), '%'.$this->searchQuery.'%');
            }
        }
        if(!$items) {
            $this->armory->Log()->writeLog('%s : unable to find any items with `%s` query (current locale: %s, locId: %d)', __METHOD__, $this->searchQuery, $this->armory->GetLocale(), $this->armory->GetLoc());
            return false;
        }
        $result_data = array();
        $i = 0;
        $item_source = self::GetItemSourceArray($items);
        foreach($items as $item) {
            $result_data[$i]['data'] = $item;
            $result_data[$i]['data']['icon'] = Items::GetItemIcon($item['id'], $item['displayid']);
            if(self::CanAuction($item)) {
                $result_data[$i]['data']['canAuction'] = 1;
            }
            unset($result_data[$i]['data']['flags'], $result_data[$i]['data']['duration'], $result_data[$i]['data']['bonding']);
            if($this->armory->GetLocale() == 'en_gb' || $this->armory->GetLocale() == 'en_us') {
                $result_data[$i]['data']['name'] = $item['name'];
            }
            else {
                $result_data[$i]['data']['name'] = Items::GetItemName($item['id']);
            }
            $result_data[$i]['filters'] = array(
                array('name' => 'itemLevel', 'value' => $item['ItemLevel']),
                array('name' => 'relevance', 'value' => 100)
            );
            if(isset($item_source[$item['id']])) {
                $tmp_src = $item_source[$item['id']];
                if($tmp_src['source'] == 'sourceType.dungeon' && $tmp_src['areaKey'] != null && $tmp_src['areaUrl'] != null) {
                    $result_data[$i]['filters'][] = array('areaId' => $tmp_src['areaId'], 'areaKey' => $tmp_src['areaKey'], 'areaName' => $tmp_src['areaName'], 'name' => 'source', 'value' => 'sourceType.creatureDrop');
                }
                else {
                    $result_data[$i]['filters'][] = array('name' => 'source', 'value' => $tmp_src['source']);
                }
            }
            else {
                $result_data[$i]['filters'][] = array('name' => 'source', 'value' => 'sourceType.none');
            }
            if($this->heirloom == true) {
                $result_data[$i]['filters'][] = array('name' => 'source', 'value' => 'sourceType.vendor');
            }
            $i++;
            unset($result_data[$i]['data']['ItemLevel']);
        }
        return $result_data;
    }
    
    public function PerformAdvancedItemsSearch($count = false) {
        if($this->itemSearchSkip == true) {
            return false;
        }
        if((!$this->get_array || !is_array($this->get_array)) && !$this->searchQuery ) {
            $this->armory->Log()->writeError('%s : start failed', __METHOD__);
            return false;
        }
        if(!isset($this->get_array['source'])) {
            $this->armory->Log()->writeError('%s : get_array[source] not defined', __METHOD__);
            return false;
        }
        $allowedDungeon = false;
        // Get item IDs first (if $this->searchQuery is defined)
        $item_id_string = null;
        if($this->searchQuery) {
            if($this->armory->GetLoc() == 0) {
                // No SQL injection - already escaped in search.php
                $_item_ids = $this->armory->wDB->select("SELECT `entry` FROM `item_template` WHERE `name` LIKE '%s'", '%'.$this->searchQuery.'%');
            }
            else {
                $_item_ids = $this->armory->wDB->select("SELECT `entry` FROM `item_template` WHERE `name` LIKE '%s' OR `entry` IN (SELECT `entry` FROM `locales_item` WHERE `name_loc%d` LIKE '%s')", '%'.$this->searchQuery.'%', $this->armory->GetLoc(), '%' . $this->searchQuery.'%');
            }
            if(is_array($_item_ids)) {
                $tmp_count_ids = count($_item_ids);
                for($i = 0; $i < $tmp_count_ids; $i++) {
                    if($i) {
                        $item_id_string .= ', ' . $_item_ids[$i]['entry'];
                    }
                    else {
                        $item_id_string .= $_item_ids[$i]['entry'];
                    }
                }
                unset($tmp_count_ids, $_item_ids);
            }
        }
        switch($this->get_array['source']) {
            case 'all':
                $global_sql_query = $this->HandleItemFilters($item_id_string);
                break;
            case 'quest':
                $tmp_quest_query = "SELECT `item` FROM `ARMORYDBPREFIX_source` WHERE `source`='sourceType.questReward'";
                if($item_id_string != '') {
                    $tmp_quest_query .= sprintf(" AND `item` IN (%s)", $item_id_string);
                }
                $tmp_quest_query .= " ORDER BY `item` DESC LIMIT 200";
                $_quest_items = $this->armory->aDB->select($tmp_quest_query);
                if(!$_quest_items) {
                    return false;
                }
                $quest_id_string = '';
                $qCount = count($_quest_items);
                for($i = 0; $i < $qCount; $i++) {
                    if($i) {
                        $quest_id_string .= ', ' . $_quest_items[$i]['item'];
                    }
                    else {
                        $quest_id_string .= $_quest_items[$i]['item'];
                    }
                }
                unset($_quest_items, $qCount);
                $global_sql_query = $this->HandleItemFilters($quest_id_string);
                break;
            case 'dungeon':
                if(!isset($this->get_array['dungeon'])) {
                    $this->get_array['dungeon'] = 'all';
                }
                if(!isset($this->get_array['difficulty'])) {
                    $this->get_array['difficulty'] = 'all';
                }
                if(!isset($this->get_array['boss'])) {
                    $this->get_array['boss'] = 'all';
                }
                if(self::IsExtendedCost()) {
                    $this->armory->Log()->writeLog('%s : current ExtendedCost key: %s', __METHOD__, $this->get_array['dungeon']);
                    $item_extended_cost = $this->armory->aDB->selectCell("SELECT `item` FROM `ARMORYDBPREFIX_item_sources` WHERE `key`='%s' LIMIT 1", $this->get_array['dungeon']);
                    if(!$item_extended_cost) {
                        $this->armory->Log()->writeError('%s : this->get_array[dungeon] is ExtendedCost key (%s) but data for this key is missed in `armory_item_sources`', __METHOD__, $this->get_array['dungeon']);
                        return false;
                    }
                    $extended_cost = $this->armory->aDB->select("SELECT `id` FROM `ARMORYDBPREFIX_extended_cost` WHERE `item1`=%d OR `item2`=%d OR `item3`=%d OR `item4`=%d OR `item5`=%d", $item_extended_cost, $item_extended_cost, $item_extended_cost, $item_extended_cost, $item_extended_cost);
                    if(!$extended_cost) {
                        $this->armory->Log()->writeError('%s : this->get_array[dungeon] is ExtendedCost (key: %s, id: %d) but data for this id is missed in `armory_extended_cost`', __METHOD__, $this->get_array['dungeon'], $item_extended_cost);
                        return false;
                    }
                    $cost_ids = array();
                    foreach($extended_cost as $cost) {
                        $cost_ids[] = $cost['id'];
                    }
                    $ex_cost_ids = null;
                    $mytmpcount = count($cost_ids);
                    for($i = 0; $i < $mytmpcount; $i++) {
                        if($i) {
                            $ex_cost_ids .= ', ' . $cost_ids[$i] .', -' . $cost_ids[$i];
                        }
                        else {
                            $ex_cost_ids .= $cost_ids[$i].', -' . $cost_ids[$i];
                        }
                    }
                    $global_sql_query = $this->HandleItemFilters($item_id_string, $ex_cost_ids);
                }
                else {
                    $allowedDungeon = true;     
                    $instance_data = Utils::GetDungeonData($this->get_array['dungeon']);
                    if(!is_array($instance_data) || !isset($instance_data['difficulty'])) {
                        return false;
                    }
                    switch($this->get_array['difficulty']) {
                        case 'normal':
                            switch($instance_data['difficulty']) {
                                case 2:  // 25 Man (icc/toc)
                                    $sql_query = "SELECT `lootid_2`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    break;
                                default: // 10 Man (icc/toc) / all others
                                    $sql_query = "SELECT `lootid_1`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    break;
                            }
                            $difficulty = 'n';
                            break;
                        case 'heroic':
                            switch($instance_data['difficulty']) { // instance diffuclty, not related to get_array['difficulty']
                                case 1: // 10 Man (icc/toc)
                                    $sql_query = "SELECT `lootid_3`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    break;
                                case 2: // 25 Man (icc/toc)
                                    $sql_query = "SELECT `lootid_4`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    break;
                                default: // All others
                                    $sql_query = "SELECT `lootid_2`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    break;
                            }
                            $difficulty = 'h';
                            break;
                        // All
                        default:
                            $difficulty = null;
                            switch($instance_data['difficulty']) {
                                case 1: // 10 Man
                                    if($instance_data['is_heroic'] == 1) {
                                        $sql_query = "SELECT `lootid_1`, `lootid_3`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    }
                                    else {
                                        $sql_query = "SELECT `lootid_1`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    }
                                    break;
                                case 2: // 25 Man
                                    if($instance_data['is_heroic'] == 1) {
                                        $sql_query = "SELECT `lootid_2`, `lootid_4`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    }
                                    else {
                                        $sql_query = "SELECT `lootid_2`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    }
                                    break;
                                default:
                                    if($instance_data['is_heroic'] == 1) {
                                        $sql_query = "SELECT `lootid_1`, `lootid_2`, `lootid_3`, `lootid_4`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    }
                                    else {
                                        $sql_query = "SELECT `lootid_1`, `lootid_2`, `type` FROM `ARMORYDBPREFIX_instance_data`";
                                    }
                                    break;
                            }
                            break;
                    }
                    if(isset($this->get_array['dungeon']) && $this->get_array['dungeon'] != 'all' && !empty($this->get_array['dungeon'])) {
                        $instance_id = Utils::GetDungeonId($this->get_array['dungeon']);
                        $sql_query .= sprintf(" WHERE `instance_id`=%d", $instance_id);
                    }
                    if(isset($this->get_array['boss']) && $this->get_array['boss'] != 'all' && !empty($this->get_array['boss'])) {
                        if(is_numeric($this->get_array['boss'])) {
                            $sql_query .= sprintf(" AND (`id`=%d OR `lootid_1`=%d OR `lootid_2`=%d OR `lootid_3`=%d OR `lootid_4`=%d)", $this->get_array['boss'], $this->get_array['boss'], $this->get_array['boss'], $this->get_array['boss'], $this->get_array['boss']);
                        }
                        else {
                            $sql_query .= sprintf(" AND `key`='%s'", $this->get_array['boss']);
                        }
                    }
                    $boss_lootids = $this->armory->aDB->select($sql_query);
                    if(!$boss_lootids) {
                        $this->armory->Log()->writeLog('%s : unable to find loot IDs for boss %s (instance: %s)', __METHOD__, $this->get_array['boss'], $this->get_array['dungeon']);
                        return false;
                    }
                    $loot_table = '-1';
                    foreach($boss_lootids as $loot_id) {
                        for($i = 1; $i < 5; $i++) {
                            if(isset($loot_id['lootid_'.$i])) {
                                if($i) {
                                    $loot_table .= ', '.$loot_id['lootid_'.$i];
                                }
                                else {
                                    $loot_table .= $loot_id['lootid_'.$i];
                                }
                            }
                        }
                    }
                    $global_sql_query = $this->HandleItemFilters($item_id_string, $loot_table);
                }
                break;
            case 'reputation':
                if(!isset($this->get_array['faction'])) {
                    $this->get_array['faction'] = 'all';
                }
                $global_sql_query = $this->HandleItemFilters($item_id_string, $this->get_array['faction']);
                break;
            case 'pvpAlliance':
            case 'pvpHorde':
                if(!isset($this->get_array['pvp'])) {
                    $this->get_array['pvp'] = 'all';
                }
                if($this->get_array['pvp'] == 'all' || $this->get_array['pvp'] == -1) {
                    $pvpVendorsId = $this->armory->aDB->select("SELECT `item` FROM `ARMORYDBPREFIX_item_sources` WHERE `key` IN ('wintergrasp', 'arena8', 'arena7', 'arena6', 'arena5', 'arena4', 'arena3', 'arena2', 'arena1', 'honor', 'ab', 'av', 'wsg', 'halaa', 'honorhold', 'terrokar', 'zangarmarsh', 'thrallmar')");
                    if(!$pvpVendorsId) {
                        $this->armory->Log()->writeError('%s : unable to get data for pvpVendors from armory_item_sources', __METHOD__);
                        return false;
                    }
                }
                else {
                    $pvpVendorsId = $this->armory->aDB->select("SELECT `item` FROM `ARMORYDBPREFIX_item_sources` WHERE `key`='%s'", $this->get_array['pvp']);
                    if(!$pvpVendorsId) {
                        $this->armory->Log()->writeError('%s : unable to get data for pvpVendors from armory_item_sources (faction: %s, key: %s)', __METHOD__, $this->get_array['source'], $this->get_array['pvp']);
                        return false;
                    }
                }
                $countVendors = count($pvpVendorsId);
                $string_vendors = null;
                for($i = 0; $i < $countVendors; $i++) {
                    $tmpVendID = explode('/', $pvpVendorsId[$i]['item']);
                    if(is_array($tmpVendID) && isset($tmpVendID[0]) && isset($tmpVendID[1])) {
                        if($this->get_array['source'] == 'pvpAlliance') {
                            $vendors_id = $tmpVendID[0];
                        }
                        else {
                            $vendors_id = $tmpVendID[1];
                        }
                    }
                    else {
                        $vendors_id = $pvpVendorsId[$i]['item'];
                    }
                    if($i) {
                        $string_vendors .= ', ' . $vendors_id; 
                    }
                    else {
                        $string_vendors .= $vendors_id;
                    }
                }
                $global_sql_query = $this->HandleItemFilters($item_id_string, $string_vendors);
                break;
        }
        if(!isset($global_sql_query) || !$global_sql_query) {
            $this->armory->Log()->writeError('%s : SQL query was not created (probably "%s" is unknown source).', __METHOD__, $this->get_array['source']);
            return false;
        }
        $items_query = $this->armory->wDB->select($global_sql_query);
        if(!$items_query) {
            $this->armory->Log()->writeError('%s : unable to execute SQL query "%s"', __METHOD__, $global_sql_query);
            unset($global_sql_query);
            return false;
        }
        $items_result = array();
        $exists_items = array();
        $source_items = self::GetItemSourceArray($items_query);
        $i = 0;
        foreach($items_query as $item) {
            if(isset($exists_items[$item['id']])) {
                continue; // Do not add the same items to result array
            }
            if($i >= 200) {
                if($count) {
                    return count($exists_items);
                }
                else {
                    return $items_result;
                }
            }
            elseif(!$count) {
                $tmp_src = (isset($source_items[$item['id']])) ? $source_items[$item['id']] : null;
                $items_result[$i]['data'] = array();
                $items_result[$i]['filters'] = array();
                $items_result[$i]['data']['id'] = $item['id'];
                if($this->armory->GetLocale() != 'en_gb' || $this->armory->GetLocale() != 'en_us') {
                    $items_result[$i]['data']['name'] = Items::GetItemName($item['id']);
                }
                else {
                    $items_result[$i]['data']['name'] = $item['name'];
                }
                if(self::CanAuction($item)) {
                    $items_result[$i]['data']['canAuction'] = 1;
                }
                $items_result[$i]['data']['rarity'] = $item['rarity'];
                $items_result[$i]['data']['icon'] = Items::GetItemIcon($item['id'], $item['displayid']);
                $items_result[$i]['filters'][0] = array('name' => 'itemLevel', 'value' => $item['ItemLevel']);
                $items_result[$i]['filters'][1] = array('name' => 'relevance', 'value' => 100); // TODO: add relevance calculation for items
                // Add some filters (according with item source)
                if($tmp_src != null) {
                    if($tmp_src['source'] == 'sourceType.dungeon' && $tmp_src['areaKey'] != null && $tmp_src['areaUrl'] != null) {
                        $items_result[$i]['filters'][] = array('areaId' => $tmp_src['areaId'], 'areaKey' => $tmp_src['areaKey'], 'areaName' => $tmp_src['areaName'], 'name' => 'source', 'value' => 'sourceType.creatureDrop');
                    }
                    else {
                        $items_result[$i]['filters'][] = array('name' => 'source', 'value' => $tmp_src['source']);
                    }
                }
                else {
                    switch($this->get_array['source']) {
                        case 'reputation':
                            $items_result[$i]['filters'][] = array('name' => 'source', 'value' => 'sourceType.factionReward');
                            break;
                        case 'quest':
                            $items_result[$i]['filters'][] = array('name' => 'source', 'value' => 'sourceType.questReward');
                            break;
                        case 'pvpAlliance':
                        case 'pvpHorde':
                            $items_result[$i]['filters'][2] = array('name' => 'source', 'value' => 'sourceType.vendor');
                            break;
                    }
                }
            }
            $exists_items[$item['id']] = $item['id'];
            $i++;
        }
        if($count == true) {
            return count($exists_items);
        }
        return $items_result;
    }
    
    public function PerformArenaTeamsSearch($num = false) {
        if(!$this->searchQuery) {
            return false;
        }
        $results = array(); // Full results
        $current_realm = array();
        $count_results = 0; // All realms results
        $count_results_currrent_realm = 0; // Current realm results
        $db = null; // Temporary handler
        if($num == true) {
            foreach($this->armory->realmData as $realm_info) {
                $count_results_currrent_realm = 0;
                $db = new ArmoryDatabaseHandler($realm_info['host_characters'], $realm_info['user_characters'], $realm_info['pass_characters'], $realm_info['name_characters'], $realm_info['charset_characters'], $this->armory->Log());
                $count_results_currrent_realm = $db->selectCell("SELECT COUNT(`arenateamid`) FROM `arena_team` WHERE `name` LIKE '%s' LIMIT 200", '%'.$this->searchQuery.'%');
                $count_results = $count_results + $count_results_currrent_realm;
            }
            return $count_results;
        }
        foreach($this->armory->realmData as $realm_info) {
            $db = new ArmoryDatabaseHandler($realm_info['host_characters'], $realm_info['user_characters'], $realm_info['pass_characters'], $realm_info['name_characters'], $realm_info['charset_characters'], $this->armory->Log());
            $current_realm = $db->select("
            SELECT `arena_team`.`name`, `arena_team`.`type` AS `size`, `arena_team_stats`.`rating`, `characters`.`race`
                FROM `arena_team` AS `arena_team`
                    LEFT JOIN `arena_team_stats` AS `arena_team_stats` ON `arena_team`.`arenateamid`=`arena_team_stats`.`arenateamid`
                    LEFT JOIN `characters` AS `characters` ON `arena_team`.`captainguid`=`characters`.`guid`
                        WHERE `arena_team`.`name` LIKE '%s' LIMIT 200", '%'.$this->searchQuery.'%');
            if(!$current_realm) {
                continue;
            }
            $count_current_realm = count($current_realm);
            foreach($current_realm as $realm) {
                $realm['teamSize'] = $realm['size'];
                $realm['battleGroup'] = $this->armory->armoryconfig['defaultBGName'];
                $realm['factionId'] = Utils::GetFactionId($realm['race']);
                $realm['relevance'] = 100;
                $realm['realm'] = $realm_info['name'];
                $realm['url'] = sprintf('r=%s&ts=%d&t=%s', urlencode($realm_info['name']), $realm['size'], urlencode($realm['name']));
                unset($realm['race']);
                $results[] = $realm;
            }
        }
        if($results) {
            return $results;
        }
        return false;
    }
    
    public function PerformGuildsSearch($num = false) {
        if(!$this->searchQuery) {
            return false;
        }
        $results = array(); // Full results
        $current_realm = array();
        $count_results = 0; // All realms results
        $count_results_currrent_realm = 0; // Current realm results
        $db = null; // Temporary handler
        if($num == true) {
            foreach($this->armory->realmData as $realm_info) {
                $count_results_currrent_realm = 0;
                $db = new ArmoryDatabaseHandler($realm_info['host_characters'], $realm_info['user_characters'], $realm_info['pass_characters'], $realm_info['name_characters'], $realm_info['charset_characters'], $this->armory->Log());
                $count_results_currrent_realm = $db->selectCell("SELECT COUNT(`guildid`) FROM `guild` WHERE `name` LIKE '%s' LIMIT 200", '%'.$this->searchQuery.'%');
                $count_results = $count_results + $count_results_currrent_realm;
            }
            return $count_results;
        }
        foreach($this->armory->realmData as $realm_info) {
            $db = new ArmoryDatabaseHandler($realm_info['host_characters'], $realm_info['user_characters'], $realm_info['pass_characters'], $realm_info['name_characters'], $realm_info['charset_characters'], $this->armory->Log());
            $current_realm = $db->select("SELECT `guild`.`name`, `characters`.`race` FROM `guild` AS `guild` LEFT JOIN `characters` AS `characters` ON `guild`.`leaderguid`=`characters`.`guid` WHERE `guild`.`name` LIKE '%s' LIMIT 200", '%'.$this->searchQuery.'%');
            if(!$current_realm) {
                continue;
            }
            $count_current_realm = count($current_realm);
            foreach($current_realm as $realm) {
                $realm['battleGroup'] = $this->armory->armoryconfig['defaultBGName'];
                $realm['factionId'] = Utils::GetFactionId($realm['race']);
                $realm['relevance'] = 100; // All guilds have 100% relevance
                $realm['realm'] = $realm_info['name'];
                $realm['url'] = sprintf('r=%s&gn=%s', urlencode($realm_info['name']), urlencode($realm['name']));
                unset($realm['race']);
                $results[] = $realm;
            }
        }
        if($results) {
            return $results;
        }
        return false;
    }
    
    public function PerformCharactersSearch($num = false) {
        if(!$this->searchQuery) {
            $this->armory->Log()->writeLog('%s : searchQuery not defined', __METHOD__);
            return false;
        }
        $currentTimeStamp = time();
        $results = array(); // Full results
        $current_realm = array();
        $count_results = 0; // All realms results
        $count_results_currrent_realm = 0; // Current realm results
        $db = null; // Temporary handler
        $countRealmData = count($this->armory->realmData);
        if($num == true) {
            foreach($this->armory->realmData as $realm_info) {
                $count_results_currrent_realm = 0;
                $db = new ArmoryDatabaseHandler($realm_info['host_characters'], $realm_info['user_characters'], $realm_info['pass_characters'], $realm_info['name_characters'], $realm_info['charset_characters'], $this->armory->Log());
                $characters_data[] = $db->select("SELECT `guid`, `level`, `account` FROM `characters` WHERE `name`='%s' AND `level` >= %d LIMIT 200", $this->searchQuery, $this->armory->armoryconfig['minlevel']);
            }
            for($ii = 0; $ii < $countRealmData; $ii++) {
                $count_result_chars = count($characters_data[$ii]);
                for($i=0;$i<$count_result_chars;$i++) {
                    if(isset($characters_data[$ii][$i]) && self::IsCharacterAllowedForSearch($characters_data[$ii][$i]['guid'], $characters_data[$ii][$i]['level'], $characters_data[$ii][$i]['account'])) {
                        $count_results++;
                    }
                }
            }
            return $count_results;
        }
        $accounts_cache = array(); // For relevance calculation
        foreach($this->armory->realmData as $realm_info) {
            $db = new ArmoryDatabaseHandler($realm_info['host_characters'], $realm_info['user_characters'], $realm_info['pass_characters'], $realm_info['name_characters'], $realm_info['charset_characters'], $this->armory->Log());
            if(!$db) {
                continue;
            }
            $current_realm = $db->select("SELECT `guid`, `name`, `class` AS `classId`, `gender` AS `genderId`, `race` AS `raceId`, `level`, `account` FROM `characters` WHERE `name` = '%s'", $this->searchQuery);
            if(!$current_realm) {
                continue;
            }
            $count_current_realm = count($current_realm);
            foreach($current_realm as $realm) {
                if(!self::IsCharacterAllowedForSearch($realm['guid'], $realm['level'], $realm['account'])) {
                    continue;
                }
                if($realm['guildId'] = $db->selectCell("SELECT `guildid` FROM `guild_member` WHERE `guid`=%d", $realm['guid'])) {
                    $realm['guild'] = $db->selectCell("SELECT `name` FROM `guild` WHERE `guildid`=%d", $realm['guildId']);
                    $realm['guildUrl'] = sprintf('r=%s&gn=%s', urlencode($realm_info['name']), urlencode($realm['guild']));
                }
                $realm['url'] = sprintf('r=%s&cn=%s', urlencode($realm_info['name']), urlencode($realm['name']));
                $realm['battleGroup'] = $this->armory->armoryconfig['defaultBGName'];
                $realm['battleGroupId'] = 1;
                $realm['class'] = $this->armory->aDB->selectCell("SELECT `name_%s` FROM `ARMORYDBPREFIX_classes` WHERE `id`=%d", $this->armory->GetLocale(), $realm['classId']);
                $realm['race'] = $this->armory->aDB->selectCell("SELECT `name_%s` FROM `ARMORYDBPREFIX_races` WHERE `id`=%d", $this->armory->GetLocale(), $realm['raceId']);
                $realm['realm'] = $realm_info['name'];
                $realm['factionId'] = Utils::GetFactionId($realm['raceId']);
                $realm['searchRank'] = 1; //???
                /* Calculate relevance */
                $realm['relevance'] = 100;
                // Relevance by last login date will check `realmd`.`account`.`last_login` timestamp
                // First of all - check character level
                $temp_value = $realm['level'];
                if($temp_value > 70 && $temp_value < PLAYER_MAX_LEVEL) {
                    $realm['relevance'] -= 20;
                }
                elseif($temp_value > 60 && $temp_value < 70) {
                    $realm['relevance'] -= 25;
                }
                elseif($temp_value > 50 && $temp_value < 60) {
                    $realm['relevance'] -= 30;
                }
                elseif($temp_value > 40 && $temp_value < 50) {
                    $realm['relevance'] -= 35;
                }
                elseif($temp_value > 30 && $temp_value < 40) {
                    $realm['relevance'] -= 40;
                }
                elseif($temp_value > 20 && $temp_value < 30) {
                    $realm['relevance'] -= 45;
                }
                elseif($temp_value < 20) {
                    $realm['relevance'] -= 50;
                    // characters with level < 20 have 50% relevance and other reasons can't change this value
                    unset($realm['account'], $realm['guid']);
                    $results[] = $realm;
                    continue;
                }
                // Check last login date. If it's more than 2 days, decrease relevance by 4 for every day
                if(!isset($accounts_cache[$realm['account']])) {
                    $lastLogin = $this->armory->rDB->selectCell("SELECT `last_login` FROM `account` WHERE `id`=%d", $realm['account']);
                    $accounts_cache[$realm['account']] = $lastLogin;
                }
                else {
                    $lastLogin = $accounts_cache[$realm['account']];
                }
                $lastLoginTimestamp = strtotime($lastLogin);
                $diff = $currentTimeStamp - $lastLoginTimestamp;
                if($lastLogin && $diff > 0) {
                    // 1 day is 86400 seconds
                    $totalDays = round($diff/86400);
                    if($totalDays > 2) {
                        $decreaseRelevanceByLogin = $totalDays*4;
                        $realm['relevance'] -= $decreaseRelevanceByLogin;
                    }
                }
                // Relevance for characters can't be less than 50
                if($realm['relevance'] < 50) {
                    $realm['relevance'] = 50;
                }
                // Relevance can't be more than 100
                if($realm['relevance'] > 100) {
                    $realm['relevance'] = 100;
                }
                unset($realm['account'], $realm['guid']);
                $results[] = $realm;
            }
        }
        if($results) {
            return $results;
        }
        return false;
    }
    
    public function IsExtendedCost() {
        if(!isset($this->get_array['dungeon'])) {
            $this->armory->Log()->writeError('%s is for `dungeon` cases only!', __METHOD__);
            return false;
        }
        $key = $this->get_array['dungeon'];
        if($key == 'emblemoffrost' || $key == 'emblemoftriumph' || $key == 'emblemofconquest' || $key == 'emblemofvalor' || $key == 'emblemofheroism' || $key == 'badgeofjustice') {
            return true;
        }
        return false;
    }
    
    public function MakeUniqueArray($array, $preserveKeys = false) {
        // Unique Array for return  
        $arrayRewrite = array();  
        // Array with the md5 hashes  
        $arrayHashes = array();  
        foreach($array as $key => $item) {
            // Serialize the current element and create a md5 hash  
            $hash = md5(serialize($item));
            // If the md5 didn't come up yet, add the element to  
            // to arrayRewrite, otherwise drop it  
            if (!isset($arrayHashes[$hash])) {
                // Save the current element hash  
                $arrayHashes[$hash] = $hash;  
                // Add element to the unique Array  
                if ($preserveKeys) {
                    $arrayRewrite[$key] = $item;
                } else {  
                    $arrayRewrite[] = $item;
                }
            }
        }
        return $arrayRewrite;  
    }
    
    private function IsCharacterAllowedForSearch($guid, $level, $account_id) {
        if($level < $this->armory->armoryconfig['minlevel']) {
            return false;
        }
        $gmLevel = $this->armory->rDB->selectCell("SELECT `gmlevel` FROM `account` WHERE `id`=%d LIMIT 1", $account_id);
        if($gmLevel <= $this->armory->armoryconfig['minGmLevelToShow']) {
            return true;
        }
        return false;
    }
    
    /**
     * Helper
     **/
    private function CanAuction($item_data) {
        //                      Undef BoE BoU
        $allowed_bondings = array(0,   2,  3);
        if(!in_array($item_data['bonding'], $allowed_bondings)) {
            // Wrong bonding type
            return false;
        }
        elseif($item_data['flags']&0x00000002) {
            // Conjured items can't be traded via auction.
            return false;
        }
        elseif($item_data['duration'] > 0) {
            // Items with duration can't be traded via auction.
            return false;
        }
        else {
            //$this->armory->Log()->writeLog('%s : item #%d can be traded via auction', __METHOD__, $item_data['id']);
            return true;
        }
    }
    
    /**
     * Do all dirty work with search filters here
     * Can be called only from Search::PerformAdvancedItemsSearch()
     * @category Search class
     * @access   private
     * @return   bool
     **/
    private function HandleItemFilters($item_ids = null, $data = null) {
        if(!isset($_SERVER['QUERY_STRING'])) {
            $this->armory->Log()->writeError('%s : unable to find \$_SERVER[QUERY_STRING] variable', __METHOD__);
            return false;
        }
        elseif(!$this->get_array) {
            $this->armory->Log()->writeError('%s : get_array not defined', __METHOD__);
            return false;
        }
        $string = $_SERVER['QUERY_STRING'];
        $query_string = explode('&', $string);
        if(!is_array($query_string)) {
            $this->armory->Log()->writeError('%s : unable to convert \$string variable to array (query_string)!', __METHOD__);
        }
        $search_type = (isset($this->get_array['andor'])) ? $this->get_array['andor'] : 'and';
        $quality_types = array(
            'pr' => 0,
            'cn' => 1,
            'un' => 2,
            're' => 3,
            'ec' => 4,
            'lg' => 5,
            'hm' => 7
        );
        // Parse
        $count = count($query_string);
        $sql = "SELECT 
        `item_template`.`entry` AS `id`,
        `item_template`.`name`,
        `item_template`.`ItemLevel`,
        `item_template`.`Quality` AS `rarity`, 
        `item_template`.`displayid`, 
        `item_template`.`bonding`, 
        `item_template`.`flags`, 
        `item_template`.`duration`";
        if($this->get_array['source'] == 'dungeon' || $this->get_array['source'] == 'pvpAlliance' || $this->get_array['source'] == 'pvpHorde') {
            if(self::IsExtendedCost() == false && $this->get_array['source'] == 'dungeon') {
                $sql .= ",
                `creature_loot_template`.`entry`,
                `creature_loot_template`.`item`,
                `creature_loot_template`.`ChanceOrQuestChance`
                FROM `item_template` AS `item_template`
                LEFT JOIN `creature_loot_template` AS `creature_loot_template` ON `creature_loot_template`.`item`=`item_template`.`entry`
                WHERE";
            }
            else {
                $sql .= ",
                `npc_vendor`.`item`,
                `npc_vendor`.`entry`
                FROM `item_template` AS `item_template`
                LEFT JOIN `npc_vendor` AS `npc_vendor` ON `npc_vendor`.`item`=`item_template`.`entry`
                WHERE";
            }
        }
        else {
            $sql .= "
            FROM `item_template` WHERE";
        }
        if(isset($this->get_array['rqrMin']) && isset($this->get_array['rqrMax']) && ($this->get_array['rqrMin'] > 0 || $this->get_array['rqrMax'] > 0) /*<= PLAYER_MAX_LEVEL*/) {
            if($this->get_array['rqrMin'] > 0 && $this->get_array['rqrMax'] > 0) {
                $sql .= sprintf(" (`item_template`.`RequiredLevel` >= %d AND `item_template`.`RequiredLevel` <= %d) AND", (int) $this->get_array['rqrMin'], (int) $this->get_array['rqrMax']);
            }
            elseif($this->get_array['rqrMin'] > 0 && (!$this->get_array['rqrMax'] || $this->get_array['rqrMax'] == 0)) {
                $sql .= sprintf(" (`item_template`.`RequiredLevel` >= %d) AND", (int) $this->get_array['rqrMin']);
            }
            elseif($this->get_array['rqrMax'] > 0 && (!$this->get_array['rqrMin'] || $this->get_array['rqrMin'] == 0)) {
                $sql .= sprintf(" (`item_template`.`RequiredLevel` <= %d) AND", (int) $this->get_array['rqrMax']);
            }
        }
        if(isset($this->get_array['rrt']) && isset($quality_types[$this->get_array['rrt']])) {
            $sql .= sprintf("(`item_template`.`Quality`=%d) AND", $quality_types[$this->get_array['rrt']]);
        }
        if(isset($this->get_array['type'])) {
            $type_info = -1;
            $subType_info = -1;
            if($this->get_array['type'] != 'all') {
                $type_info = Items::GetItemTypeInfo($this->get_array['type'], 'type');
            }
            if((isset($this->get_array['subTp'])) && $this->get_array['subTp'] != 'all') {
                $subType_info = Items::GetItemTypeInfo($this->get_array['subTp'], 'subtype');
            }
            if($type_info != -1 && $subType_info != -1)  {
                $sql .= sprintf(" (`item_template`.`class`='%d' AND `item_template`.`subclass`='%d') AND", $type_info, $subType_info);
            }
            elseif($type_info != -1) {
                $sql .= sprintf(" (`item_template`.`class`='%d') AND", $type_info);
            }
        }
        if(isset($this->get_array['usbleBy']) && $this->get_array['usbleBy'] > 0) {
            $sql .= sprintf(" (`item_template`.`AllowableClass`&%d) AND", Utils::GetClassBitMaskByClassId($this->get_array['usbleBy']));
            if(isset($this->get_array['type'])) {
                if(!isset($this->get_array['subTp']) || $this->get_array['subTp'] == 'all') {
                    $allowable_types = null;
                    switch($this->get_array['type']) {
                        case 'armor':
                            $allowable_types = Utils::GetAllowableArmorTypesForClass($this->get_array['usbleBy'], true);
                            break;
                        default:
                            $allowable_types = Utils::GetAllowableWeaponTypesForClass($this->get_array['usbleBy'], true);
                            break;
                    }
                    if($allowable_types != null) {
                        $sql .= sprintf(" (`item_template`.`subclass` IN (%s)) AND", $allowable_types);
                    }
                }
            }
        }
        for($i = 0; $i < $count; $i++) {
            if(!isset($query_string[$i])) {
                continue;
            }
            $opt_value = $query_string[$i];
            $current = explode('=', $opt_value);
            if(!is_array($current) || !isset($current[0]) || !isset($current[1])) {
                continue;
            }
            switch($current[0]) {
                case 'classType':
                    if($current[1] != 'all') {
                        $type_Result = Items::GetItemModsByClassType($current[1], 1, '>', 'OR');
                        if($type_Result) {
                            $sql .= sprintf(" (%s) AND", $type_Result);
                        }
                    }
                    break;
                case 'slot':
                    if($current[1] != 'all' && $iType = Items::GetInventoryTypeBySlotName($current[1])) {
                        $sql .= sprintf(" (`item_template`.`InventoryType`=%d) AND", $iType);
                    }
                    break;
                case 'advOptName':
                    // Need to get next 2 rows (advOptOper and advOptValue)
                    $advOptOper = (isset($query_string[$i+1])) ? $query_string[$i+1] : false;
                    $advOptValue = (isset($query_string[$i+2])) ? $query_string[$i+2] : false;
                    if($advOptOper === false || $advOptValue === false) {
                        continue;
                    }
                    $advOptOper = explode('=', $advOptOper);
                    $advOptValue = explode('=', $advOptValue);
                    $advOptOper = $advOptOper[1];
                    $advOptValue = $advOptValue[1];
                    if($advOptOper == 'gt') {
                        $advOptOper = '>';
                    }
                    elseif($advOptOper == 'lt') {
                        $advOptOper = '<';
                    }
                    else {
                        $advOptOper = '=';
                    }
                    $mods_result = Items::GetItemModsByOpt($current[1], $advOptOper, $advOptValue);
                    if($mods_result) {
                        $sql .= sprintf(" (%s) AND", $mods_result);
                    }
                    break;
                default:
                    break;
            }
        }
        if($data != null && ($this->get_array['source'] == 'pvpAlliance' || $this->get_array['source'] == 'pvpHorde')) {
            $sql .= sprintf(" `npc_vendor`.`entry` IN (%s) AND", $data);
        }
        elseif($data != null && ($this->get_array['source'] == 'dungeon' && self::IsExtendedCost())) {
            $sql .= sprintf(" `npc_vendor`.`ExtendedCost` IN (%s) AND", $data);
        }
        elseif($this->get_array['source'] == 'dungeon' && self::IsExtendedCost() == false && $data != null) {
            if($item_ids != null && is_string($item_ids)) {
                $sql .= sprintf(" (`creature_loot_template`.`entry` IN (%s) AND `creature_loot_template`.`item` IN (%s)) AND", $data, $item_ids);
            }
            else {
                $sql .= sprintf(" (`creature_loot_template`.`entry` IN (%s)) AND", $data);
            }
        }
        elseif($this->get_array['source'] == 'reputation') {
            if(is_numeric($data)) {
                $sql .= sprintf(" `item_template`.`RequiredReputationFaction`=%d AND", $data);
            }
            elseif($data == 'all') {
                $sql .= " `item_template`.`RequiredReputationRank` > 0 AND";
            }
        }
        if($item_ids != null && is_string($item_ids)) {
            $sql .= sprintf(" `item_template`.`entry` IN (%s)", $item_ids);
        }
        $sql .= " ORDER BY `item_template`.`ItemLevel` DESC LIMIT 200";
        $sql = str_replace("AND ORDER BY", "ORDER BY", $sql);
        $sql = str_replace("WHERE ORDER BY", "ORDER BY", $sql);
        return $sql;
    }
    
    /**
     * Returns array with item sources (for search results)
     * @category Search class
     * @access   private
     * @param    array $items
     * @return   array
     **/
    private function GetItemSourceArray($items) {
        // Get item IDs first
        $result_ids = array();
        foreach($items as $item) {
            $curr_item_id = 0;
            if(!isset($item['id']) && !isset($item['entry'])) {
                continue;
            }
            elseif(isset($item['id'])) {
                $curr_item_id = $item['id'];
            }
            elseif(isset($item['entry'])) {
                $curr_item_id = $item['entry'];
            }
            if($curr_item_id == 0) {
                continue;
            }
            $result_ids[] = $curr_item_id;
        }
        // Get item sources
        $data = $this->armory->aDB->select("
        SELECT
        `ARMORYDBPREFIX_source`.`item`,
        `ARMORYDBPREFIX_source`.`source`,
        `ARMORYDBPREFIX_source`.`areaKey`,
        `ARMORYDBPREFIX_source`.`areaUrl`,
        `ARMORYDBPREFIX_source`.`isHeroic`,
        `ARMORYDBPREFIX_instance_template`.`name_%s` AS `areaName`,
        `ARMORYDBPREFIX_instance_template`.`id` AS `areaId`
        FROM `ARMORYDBPREFIX_source` AS `ARMORYDBPREFIX_source`
        LEFT JOIN `ARMORYDBPREFIX_instance_template` AS `ARMORYDBPREFIX_instance_template` ON `ARMORYDBPREFIX_instance_template`.`key`=`ARMORYDBPREFIX_source`.`areaKey`
        WHERE `ARMORYDBPREFIX_source`.`item` IN (%s)", $this->armory->GetLocale(), $result_ids);
        if(!$data) {
            $this->armory->Log()->writeError('%s : unable to get item sources from DB!', __METHOD__);
            return false;
        }
        $sources_result = array();
        foreach($data as $entry) {
            if(!isset($sources_result[$entry['item']])) {
                $sources_result[$entry['item']] = $entry;
            }
        }
        return $sources_result;
    }
}
?>