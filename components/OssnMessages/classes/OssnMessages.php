<?php

/**
 * Open Source Social Network
 *
 * @package   (softlab24.com).ossn
 * @author    OSSN Core Team <info@softlab24.com>
 * @copyright 2014-2017 SOFTLAB24 LIMITED
 * @license   Open Source Social Network License (OSSN LICENSE)  http://www.opensource-socialnetwork.org/licence
 * @link      https://www.opensource-socialnetwork.org/
 */
class OssnMessages extends OssnEntities {
		/**
		 * Send message
		 *
		 * @params integer $from: User 1 guid
		 * @params integer $to User 2 guid
		 * @params string $message Message
		 *
		 * @return boolean
		 */
		public function send($from, $to, $message) {
				if(empty($message) || empty($from) || empty($to)) {
						return false;
				}
				$message = strip_tags($message);
				$message = ossn_restore_new_lines($message);
				$message = ossn_input_escape($message, false);
				
				$params['into']   = 'ossn_messages';
				$params['names']  = array(
						'message_from',
						'message_to',
						'message',
						'time',
						'viewed'
				);
				$params['values'] = array(
						(int) $from,
						(int) $to,
						$message,
						time(),
						'0'
				);
				if($this->insert($params)) {
						$this->lastMessage      = $this->getLastEntry();
						if(isset($this->data) && is_object($this->data)) {
								foreach($this->data as $name => $value) {
										$this->owner_guid = $this->lastMessage;
										$this->type       = 'message';
										$this->subtype    = $name;
										$this->value      = $value;
										$this->add();
								}
						}						
						$params['message_id']   = $this->lastMessage;
						$params['message_from'] = $from;
						$params['message_to']   = $to;
						$params['message']      = $message;
						ossn_trigger_callback('message', 'created', $params);
						return true;
				}
				return false;
		}
		
		/**
		 * Mark message as viewed
		 *
		 * @params $from: User 1 guid
		 *         $to User 2 guid
		 *
		 * @return bool
		 */
		public function markViewed($from, $to) {
				$params['table']  = 'ossn_messages';
				$params['names']  = array(
						'viewed'
				);
				$params['values'] = array(
						1
				);
				$params['wheres'] = array(
						"message_from='{$from}' AND
								   message_to='{$to}'"
				);
				if($this->update($params)) {
						return true;
				}
				return false;
		}
		
		/**
		 * Get new messages
		 *
		 * @params $from: User 1 guid
		 *         $to User 2 guid
		 *
		 * @return bool
		 */
		public function getNew($from, $to, $viewed = 0) {
				$params['from']   = 'ossn_messages';
				$params['wheres'] = array(
						"message_from='{$from}' AND
								   message_to='{$to}' AND viewed='{$viewed}'"
				);
				return $this->select($params, true);
		}
		
		/**
		 * Get recently chat list
		 *
		 * @params  $to User 2 guid
		 *
		 * @return object
		 */
		public function recentChat($to, $count = false) {
				$chats  = $this->searchMessages(array(
					'wheres' => array("(message_to='{$to}' OR message_from='{$to}') AND m.message_from != '{$to}'"),		
					'order_by' => 'm.id DESC',
					'offset' => input('offset_message_xhr_recent', '', 1),
					'count' => $count,
					'group_by' => 'message_from, message_to',
				));
				if($count == true && $chats){
						return $chats;	
				}
				if(!$chats) {
						return false;
				}
				foreach($chats as $rec) {
						$recents[$rec->message_from] = $rec->message_to;
				}
				foreach($recents as $k => $v) {
						if($k !== $to) {
								$message_get = $this->get($to, $k);
								if($message_get) {
										$latest = get_object_vars($message_get);
										$c      = end($latest);
										if(!empty($c)) {
												$users[] = $c;
										}
								}
						}
				}
				if(isset($users)) {
						return $users;
				}
				return false;
		}
		/**
		 * Get messages between two users
		 *
		 * @params $from: User 1 guid
		 *         $to User 2 guid
		 *
		 * @return object
		 */
		public function getWith($from, $to, $count = false) {
				$messages =  $this->searchMessages(array(
					'wheres' => array("message_from='{$from}' AND message_to='{$to}' OR message_from='{$to}' AND message_to='{$from}'"),		
					'order_by' => 'm.id DESC',
					'offset' => input('offset_message_xhr_with', '', 1),
					'count' => $count,
				));
				if($messages && !$count){
						return array_reverse($messages);	
				}
				return $messages;
		}		
		/**
		 * Get messages between two users
		 *
		 * @params $from: User 1 guid
		 *         $to User 2 guid
		 *
		 * @return object
		 */
		public function get($from, $to) {
				$params['from']     = 'ossn_messages';
				$params['wheres']   = array(
						"message_from='{$from}' AND
								  message_to='{$to}' OR
								  message_from='{$to}' AND
								  message_to='{$from}'"
				);
				$params['order_by'] = "id ASC";
				return $this->select($params, true);
		}
		
		/**
		 * Get recent sent messages
		 *
		 * @params  $from User 1 guid
		 *
		 * @return object
		 */
		public function recentSent($from) {
				$params['from']     = 'ossn_messages';
				$params['wheres']   = array(
						"message_from='{$from}'"
				);
				$params['order_by'] = "id DESC";
				$c                  = $this->select($params, true);
				foreach($c as $rec) {
						$r[$rec->message_from] = $rec->message_to;
				}
				return $r;
		}
		
		/**
		 * Count unread messages
		 *
		 * @params  integer $to Users guid
		 *
		 * @return object
		 */
		public function countUNREAD($to) {
				$params['from']   = 'ossn_messages';
				$params['wheres'] = array(
						"message_to='{$to}' AND viewed='0'"
				);
				$params['params'] = array(
						'count(*) as new'
				);
				$count            = $this->select($params, true);
				return $count->{0}->new;
		}
		/**
		 * Get message by id
		 *
		 * @params  integer $id ID of message
		 *
		 * @return object|false
		 */
		public function getMessage($id) {
				$params['from']   = 'ossn_messages';
				$params['wheres'] = array(
						"id='{$id}'"
				);
				$get              = $this->select($params);
				if($get) {
						return $get;
				}
				return false;
		}
		/**
		 * Delete users all messages.
		 * This will also delete someone else message to this user.
		 *
		 * @params integer $guid User guid.
		 *
		 * @return boolean
		 */
		public function deleteUser($guid) {
				if(empty($guid)) {
						return false;
				}
				return $this->delete(array(
						'from' => 'ossn_messages',
						'wheres' => array(
								"message_to='{$guid}' OR message_from='{$guid}'"
						)
				));
		}
		/**
		 * Search messages by some options
		 *
		 * @param array   $params A valid options in format:
		 * @param string  $params['id']  message id
		 * @param string  $params['message_from']  A user GUID who sent messages
		 * @param string  $params['message_to']   A user GUID who receieve messages
		 * @param integer $params['viewed']  True if message is viewed , false if message isn't viewed or 1/0
		 * @param integer $params['limit'] Result limit default, Default is 20 values
		 * @param string  $params['order_by']  To show result in sepcific order. Default is DESC.
		 * @param string  $params['count']  Count the message
		 * 
		 * reutrn array|false;
		 */
		public function searchMessages(array $params = array()) {
				if(empty($params)) {
						return false;
				}
				//prepare default attributes
				$default      = array(
						'id' => false,
						'message_from' => false,
						'message_to' => false,
						'viewed' => false,
						'limit' => false,
						'order_by' => false,
						'entities_pairs' => false,
						'offset' => 1,
						'page_limit' => ossn_call_hook('pagination', 'messages:list:limit', false, 10), //call hook for page limit
						'count' => false
				);
				$options      = array_merge($default, $params);
				$wheres       = array();
				$params       = array();
				$wheres_paris = array();
				//validate offset values
				if($options['limit'] !== false && $options['limit'] !== 0 && $options['page_limit'] !== false && $options['page_limit'] !== 0) {
						$offset_vals = ceil($options['limit'] / $options['page_limit']);
						$offset_vals = abs($offset_vals);
						$offset_vals = range(1, $offset_vals);
						if(!in_array($options['offset'], $offset_vals)) {
								return false;
						}
				}
				//get only required result, don't bust your server memory
				$getlimit = $this->generateLimit($options['limit'], $options['page_limit'], $options['offset']);
				if($getlimit) {
						$options['limit'] = $getlimit;
				}
				if(!empty($options['id'])) {
						$wheres[] = "m.id='{$options['id']}'";
				}
				if(!empty($options['message_from'])) {
						$wheres[] = "m.message_from ='{$options['message_from']}'";
				}
				if(!empty($options['message_to'])) {
						$wheres[] = "m.message_to ='{$options['message_to']}'";
				}
				if(isset($options['entities_pairs']) && is_array($options['entities_pairs'])) {
						foreach($options['entities_pairs'] as $key => $pair) {
								$operand = (empty($pair['operand'])) ? '=' : $pair['operand'];
								if(!empty($pair['name']) && isset($pair['value']) && !empty($operand)) {
										if(!empty($pair['value'])) {
												$pair['value'] = addslashes($pair['value']);
										}
										$wheres_paris[] = "e{$key}.type='message'";
										$wheres_paris[] = "e{$key}.subtype='{$pair['name']}'";
										if(isset($pair['wheres']) && !empty($pair['wheres'])) {
												$pair['wheres'] = str_replace('[this].', "emd{$key}.", $pair['wheres']);
												$wheres_paris[] = $pair['wheres'];
										} else {
												$wheres_paris[] = "emd{$key}.value {$operand} '{$pair['value']}'";
												
										}
										$params['joins'][] = "INNER JOIN ossn_entities as e{$key} ON e{$key}.owner_guid=m.id";
										$params['joins'][] = "INNER JOIN ossn_entities_metadata as emd{$key} ON e{$key}.guid=emd{$key}.guid";
								}
						}
						if(!empty($wheres_paris)) {
								$wheres_entities = '(' . $this->constructWheres($wheres_paris) . ')';
								$wheres[]        = $wheres_entities;
						}
				}
				if(isset($options['wheres']) && !empty($options['wheres'])) {
						if(!is_array($options['wheres'])) {
								$wheres[] = $options['wheres'];
						} else {
								foreach($options['wheres'] as $witem) {
										$wheres[] = $witem;
								}
						}
				}
				if(isset($options['joins']) && !empty($options['joins']) && is_array($options['joins'])) {
						foreach($options['joins'] as $jitem) {
								$params['joins'][] = $jitem;
						}
				}
				$distinct = '';
				if($options['distinct'] === true) {
						$distinct = "DISTINCT ";
				}
				//prepare search    
				$params['from']     = 'ossn_messages as m';
				$params['params']   = array(
						"{$distinct}m.id",
						'm.*'
				);
				$params['wheres']   = array(
						$this->constructWheres($wheres)
				);
				$params['order_by'] = $options['order_by'];
				$params['limit']    = $options['limit'];
				
				if(!$options['order_by']) {
						$params['order_by'] = "m.id DESC";
				}
				if(isset($options['group_by']) && !empty($options['group_by'])) {
						$params['group_by'] = $options['group_by'];
				}
				//override params
				if(isset($options['params']) && !empty($options['params'])) {
						$params['params'] = $options['params'];
				}
				$messages = $this->select($params, true);
				//prepare count data;
				if($options['count'] === true) {
						unset($params['params']);
						unset($params['limit']);
						$count           = array();
						$count['params'] = array(
								"count({$distinct}m.id) as total"
						);
						$count           = array_merge($params, $count);
						return $this->select($count)->total;
				}
				if($messages) {
						foreach($messages as $message) {
								$lists = array();
								if(isset($message->id)) {
										$entities = $this->searchEntities(array(
												'type' => 'message',
												'owner_guid' => $message->id,
												'page_limit' => false
										));
										foreach($entities as $entity) {
												$lists[$entity->subtype] = $entity->value;
										}
										$merged   = array_merge((array) $message, $lists);
										$result[] = arrayObject($merged, get_class($this));
								}
						}
						return $result;
				}
				return false;
		}
} //class