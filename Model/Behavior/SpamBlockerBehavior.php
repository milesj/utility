<?php
/**
 * SpamBlockerBehavior
 *
 * A CakePHP Behavior that moderates / validates comments to check for spam.
 * Validates based on a point system. High points is an automatic approval, where as low points is marked as spam or deleted.
 * Based on Jonathon Snooks outline.
 *
 * @version		2.1.0
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 * @link        http://snook.ca/archives/other/effective_blog_comment_spam_blocker/
 */

/**
CREATE TABLE `comments` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `entry_id` INT NOT NULL,
    `status` SMALLINT NOT NULL DEFAULT 0,
    `points` INT NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(50) NOT NULL,
    `website` VARCHAR(50) NOT NULL,
    `content` TEXT NOT NULL,
    `created` DATETIME NULL DEFAULT NULL,
    `modified` DATETIME NULL DEFAULT NULL,
    INDEX (`entry_id`)
) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
*/

App::uses('ModelBehavior', 'Model');

class SpamBlockerBehavior extends ModelBehavior {

	/**
	 * Settings initiliazed with the behavior.
	 *
	 * @access public
	 * @var array
	 */
	public $settings = array(
		// Model name of the parent article
		'parent_model' => 'Entry',

		// Link to the parent article (:id and :slug will be replaced with field data)
		'article_link' => '',

		// To use a slug in the article link, use :slug
		'use_slug' => false,

		// Email address where the notify emails should go
		'notify_email' => '',

		// Should the points be saved to the database?
		'save_points' => true,

		// Should you receive a notification email for each comment?
		'send_email' => true,

		// List of blacklisted words within the author and content
		'blacklist_keys' => '',

		// List of blacklisted characters within the website and URLs
		'blacklist_chars' => '',

		// How many points till the comment is deleted (negative)
		'deletion' => -10,

		// Error message received when comment falls below threshold
		'blocked_msg' => 'Your comment has been denied.'
	);

	/**
	 * Names for table columns within the comments table and the parent.
	 *
	 * @access public
	 * @var array
	 */
	public $columns = array(
		'author'        => 'name',
		'content'       => 'content',
		'email'         => 'email',
		'website'       => 'website',
		'foreign_id'    => 'entry_id',
		'slug'          => 'slug',
		'title'         => 'title',
		'status'        => 'status',
		'points'        => 'points'
	);

	/**
	 * Status codes for the enum (or integer) status column.
	 *
	 * @access public
	 * @var array
	 */
	public $statusCodes = array(
		'pending'   => 0,
		'approved'  => 1,
		'delete'    => 2,
		'spam'      => 3
	);

	/**
	 * Disallowed words within the author and comment body.
	 *
	 * @access public
	 * @var array
	 */
	public $blacklistKeywords = array(
		'levitra', 'viagra', 'casino', 'sex', 'loan', 'finance', 'slots', 'debt', 'free', 'stock', 'debt',
		'marketing', 'rates', 'ad', 'bankruptcy', 'homeowner', 'discreet', 'preapproved', 'unclaimed',
		'email', 'click', 'unsubscribe', 'buy', 'sell', 'sales', 'earn'
	);

	/**
	 * Disallowed words/chars within URLs.
	 *
	 * @access public
	 * @var array
	 */
	public $blacklistCharacters = array('.html', '.info', '?', '&', '.de', '.pl', '.cn', '.ru', '.biz');

	/**
	 * Startup hook from the model.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $settings
	 * @return void
	 */
	public function setup($model, $settings = array()) {
		if (!empty($settings) && is_array($settings)) {
			if (!empty($settings['settings'])) {
				$this->settings = $settings['settings'] + $this->settings;
			}

			if (!empty($settings['columns'])) {
				$this->columns = $settings['columns'] + $this->columns;
			}

			if (!empty($settings['statusCodes'])) {
				$this->statusCodes = $settings['statusCodes'] + $this->statusCodes;
			}
		}

		if (!empty($this->settings['blacklist_keys']) && is_array($this->settings['blacklist_keys'])) {
			$this->blacklistKeywords = $this->settings['blacklist_keys'] + $this->blacklistKeywords;
		}

		if (!empty($this->settings['blacklist_chars']) && is_array($this->settings['blacklist_chars'])) {
			$this->blacklistCharacters = $this->settings['blacklist_chars'] + $this->blacklistCharacters;
		}
	}

	/**
	 * Runs before a save and marks the content as spam or regular comment.
	 *
	 * @access public
	 * @param Model $model
	 * @return mixed
	 */
	public function beforeSave($model) {
		$data = $model->data[$model->name];
		$points =  0;

		if (!empty($data)) {
			$referer = env('HTTP_REFERER');

			if (!empty($referer) && strpos($referer, trim(env('HTTP_HOST'), '/')) === false) {
				$points = $points - 20;
			}

			// Get links in the content
			$links = preg_match_all("#(^|[\n ])(?:(?:http|ftp|irc)s?:\/\/|www.)(?:[-A-Za-z0-9]+\.)+[A-Za-z]{2,4}(?:[-a-zA-Z0-9._\/&=+%?;\#]+)#is", $data[$this->columns['content']], $matches);
			$links = $matches[0];

			$totalLinks = count($links);
			$length = mb_strlen($data[$this->columns['content']]);

			// How many links are in the body
			// +2 if less than 2, -1 per link if over 2
			if ($totalLinks > 2) {
				$points = $points - $totalLinks;
			} else {
				$points = $points + 2;
			}

			// How long is the body
			// +2 if more then 20 chars and no links, -1 if less then 20
			if ($length >= 20 && $totalLinks <= 0) {
				$points = $points + 2;
			} else if ($length >= 20 && $totalLinks == 1) {
				++$points;
			} else if ($length < 20) {
				--$points;
			}

			// Number of previous comments from email
			// +1 per approved, -1 per spam
			$comments = $model->find('all', array(
				'fields' => array('id', $this->columns['status']),
				'conditions' => array($this->columns['email'] => $data[$this->columns['email']]),
				'recursive' => -1,
				'contain' => false
			));

			if (!empty($comments)) {
				foreach ($comments as $comment) {
					if ($comment[$model->alias][$this->columns['status']] == $this->statusCodes['spam']) {
						--$points;
					}

					if ($comment[$model->alias][$this->columns['status']] == $this->statusCodes['approved']) {
						++$points;
					}
				}
			}

			// Keyword search
			// -1 per blacklisted keyword
			foreach ($this->blacklistKeywords as $keyword) {
				if (stripos($data[$this->columns['content']], $keyword) !== false) {
					--$points;
				}
			}

			// URLs that have certain words or characters in them
			// -1 per blacklisted word
			// URL length
			// -1 if more then 30 chars
			foreach ($links as $link) {
				foreach ($this->blacklistCharacters as $character) {
					if (stripos($link, $character) !== false) {
						--$points;
					}
				}

				foreach ($this->blacklistKeywords as $keyword) {
					if (stripos($link, $keyword) !== false) {
						--$points;
					}
				}

				if (strlen($link) >= 30) {
					--$points;
				}
			}

			// Check the website, author and comment for blacklisted
			// -1 per instance
			$website = $data[$this->columns['website']];
			$content = $data[$this->columns['content']];
			$name = $data[$this->columns['author']];

			foreach ($this->blacklistCharacters as $character) {
				if (stripos($website, $character) !== false) {
					--$points;
				}
			}

			foreach ($this->blacklistKeywords as $keyword) {
				if (stripos($name, $keyword) !== false) {
					--$points;
				}

				if (stripos($content, $keyword) !== false) {
					--$points;
				}
			}

			// Body starts with...
			// -10 points
			$firstWord = mb_substr($data[$this->columns['content']], 0, stripos($data[$this->columns['content']], ' '));
			$firstDisallow = array('interesting', 'cool', 'sorry') + $this->blacklistKeywords;

			if (in_array(mb_strtolower($firstWord), $firstDisallow)) {
				$points = $points - 10;
			}

			// Author name has http:// in it
			// -2 points
			if (stripos($data[$this->columns['author']], 'http://') !== false) {
				$points = $points - 2;
			}

			// Body used in previous comment
			// -1 per exact comment
			$previousComments = $model->find('count', array(
				'conditions' => array($this->columns['content'] => $data[$this->columns['content']]),
				'recursive' => -1,
				'contain' => false
			));

			if ($previousComments > 0) {
				$points = $points - $previousComments;
			}

			// Random character match
			// -1 point per 5 consecutive consonants
			$consonants = preg_match_all('/[^aAeEiIoOuU\s]{5,}+/i', $data[$this->columns['content']], $matches);
			$totalConsonants = count($matches[0]);

			if ($totalConsonants > 0) {
				$points = $points - $totalConsonants;
			}
		}

		// Finalize and save
		if ($points >= 1) {
			$status = $this->statusCodes['approved'];
		} else if ($points == 0) {
			$status = $this->statusCodes['pending'];
		} else if ($points <= $this->settings['deletion']) {
			$status = $this->statusCodes['delete'];
		} else {
			$status = $this->statusCodes['spam'];
		}

		if ($status == $this->statusCodes['delete']) {
			$model->validationErrors[$this->columns['content']] = $this->settings['blocked_msg'];
			return false;

		} else {
			$model->data[$model->name][$this->columns['status']] = $status;

			if ($this->settings['save_points']) {
				$model->data[$model->name][$this->columns['points']] = $points;
			}

			if ($this->settings['send_email']) {
				$this->notify($data, array(
					$this->columns['status'] => $status,
					$this->columns['points'] => $points
				));
			}
		}

		return true;
	}

	/**
	 * Sends out an email notifying you of a new comment.
	 *
	 * @access public
	 * @uses Model
	 * @param array $data
	 * @param array $stats
	 * @return void
	 */
	public function notify($data, $stats) {
		if (!empty($this->settings['parent_model']) && !empty($this->settings['article_link']) && !empty($this->settings['notify_email'])) {
			$fields = array('id', $this->columns['title']);

			if ($this->settings['use_slug']) {
				$fields[] = $this->columns['slug'];
			}

			$model = ClassRegistry::init($this->settings['parent_model']);
			$entry = $model->find('first', array(
				'fields' => $fields,
				'conditions' => array('id' => $data[$this->columns['foreign_id']]),
				'recursive' => -1,
				'contain' => false
			));

			$link = str_replace(':id', $entry[$model->alias]['id'], $this->settings['article_link']);
			$title = $entry[$model->alias][$this->columns['title']];
			$statusCodes = array_flip($this->statusCodes);

			if ($this->settings['use_slug']) {
				$link = str_replace(':slug', $entry[$model->alias][$this->columns['slug']], $this->settings['article_link']);
			}

			// Build message
			$message  = "A new comment has been posted for: ". $link ."\n\n";
			$message .= "Name: ". $data[$this->columns['author']] ." <". $data[$this->columns['email']] .">\n";
			$message .= "Status: ". $statusCodes[$stats['status']] ." (". $stats['points'] ." Points)\n";
			$message .= "Message:\n\n". $data[$this->columns['content']];

			// Send email
			mail($this->settings['notify_email'], 'Comment Approval: '. $title, $message, 'From: '. $data[$this->columns['author']] .' <'. $data[$this->columns['email']] .'>');
		}
	}

}
