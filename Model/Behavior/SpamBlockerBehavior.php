<?php
/**
 * SpamBlockerBehavior
 *
 * A CakePHP Behavior that moderates and validates comments to check for spam.
 * Validation is based on a point system where high points equal an automatic approval and low points are marked as spam or deleted.
 *
 * {{{
 * 		Example model schema:
 *
 * 		CREATE TABLE `comments` (
 *			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *			`article_id` INT NOT NULL,
 *			`status` SMALLINT NOT NULL DEFAULT 0,
 *			`points` INT NOT NULL,
 *			`name` VARCHAR(50) NOT NULL,
 *			`email` VARCHAR(50) NOT NULL,
 *			`website` VARCHAR(50) NOT NULL,
 *			`content` TEXT NOT NULL,
 *			`created` DATETIME NULL DEFAULT NULL,
 *			`modified` DATETIME NULL DEFAULT NULL,
 *			INDEX (`article_id`)
 *		) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
 * }}}
 *
 * @version		3.0.0
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 * @link        http://snook.ca/archives/other/effective_blog_comment_spam_blocker/
 */

App::uses('ModelBehavior', 'Model');

class SpamBlockerBehavior extends ModelBehavior {

	/**
	 * Default settings.
	 *
	 *	model			- Model name of the parent article
	 * 	link			- Link to the parent article (:id and :slug will be replaced with field data)
	 *	email			- Email address where the notify emails should go
	 * 	useSlug			- If you want to use a slug instead of id
	 * 	savePoints		- Should the points be saved to the database?
	 * 	sendEmail		- Should you receive a notification email for each comment?
	 * 	deletion		- Amount of points till the comment is deleted (negative)
	 * 	blockMessage	- Error message received when comment falls below threshold
	 * 	keywords		- List of blacklisted keywords within the author and content
	 * 	blacklist		- List of blacklisted characters within the website and URLs
	 *	columnMap		- Names for table columns within the comments table and the parent
	 * 	statusMap		- Status codes for the enum (or integer) status column
	 *
	 * @access protected
	 * @var array
	 */
	protected $_defaults = array(
		'model' => 'Article',
		'link' => '',
		'email' => '',
		'useSlug' => false,
		'savePoints' => true,
		'sendEmail' => true,
		'deletion' => -10,
		'blockMessage' => 'Your comment has been denied and has been flagged as spam.',
		'keywords' => array(
			'levitra', 'viagra', 'casino', 'sex', 'loan', 'finance', 'slots', 'debt', 'free', 'stock', 'debt',
			'marketing', 'rates', 'ad', 'bankruptcy', 'homeowner', 'discreet', 'preapproved', 'unclaimed',
			'email', 'click', 'unsubscribe', 'buy', 'sell', 'sales', 'earn'
		),
		'blacklist' => array('.html', '.info', '.de', '.pl', '.cn', '.ru', '.biz'),
		'columnMap' => array(
			'foreignKey'	=> 'article_id',
			'id'			=> 'id',
			'author'		=> 'name',
			'content'		=> 'content',
			'email'			=> 'email',
			'website'		=> 'website',
			'slug'			=> 'slug',
			'title'			=> 'title',
			'status'		=> 'status',
			'points'		=> 'points'
		),
		'statusMap' => array(
			'pending'	=> 0,
			'approved'	=> 1,
			'deleted'	=> 2,
			'spam'		=> 3
		)
	);

	/**
	 * Merge settings.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $settings
	 * @return void
	 */
	public function setup(Model $model, $settings = array()) {
		$this->settings[$model->alias] = Hash::merge($this->_defaults, $settings);
	}

	/**
	 * Runs before a save and marks the content as spam or regular comment.
	 *
	 * @access public
	 * @param Model $model
	 * @return boolean
	 */
	public function beforeSave(Model $model) {
		$settings = $this->settings[$model->alias];
		$columnMap = $settings['columnMap'];
		$statusMap = $settings['statusMap'];

		$data = $model->data[$model->alias];
		$points = 0;

		if ($data) {
			$website = $data[$columnMap['website']];
			$content = $data[$columnMap['content']];
			$author = $data[$columnMap['author']];

			// If referrer does not come from the originating domain
			$referrer = env('HTTP_REFERER');

			if ($referrer && strpos($referrer, trim(env('HTTP_HOST'), '/')) === false) {
				$points = $points - 20;
			}

			// Get links in the content
			preg_match_all('/(^|\s|\n)(?:(?:http|ftp|irc)s?:\/\/|www\.)(?:[-a-z0-9]+\.)+[a-z\.]{2,5}/is', $content, $matches);
			$links = $matches[0];

			$totalLinks = count($links);
			$length = mb_strlen($content);

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
				'fields' => array($columnMap['id'], $columnMap['status']),
				'conditions' => array($columnMap['email'] => $data[$columnMap['email']]),
				'recursive' => -1,
				'contain' => false
			));

			if ($comments) {
				foreach ($comments as $comment) {
					if ($comment[$model->alias][$columnMap['status']] == $statusMap['spam']) {
						--$points;
					}

					if ($comment[$model->alias][$columnMap['status']] == $statusMap['approved']) {
						++$points;
					}
				}
			}

			// Keyword search
			// -1 per blacklisted keyword
			foreach ($settings['keywords'] as $keyword) {
				if (stripos($content, $keyword) !== false) {
					--$points;
				}
			}

			// URLs that have certain words or characters in them
			// -1 per blacklisted word
			// URL length
			// -1 if more then 30 chars
			foreach ($links as $link) {
				foreach ($settings['blacklist'] as $character) {
					if (stripos($link, $character) !== false) {
						--$points;
					}
				}

				foreach ($settings['keywords'] as $keyword) {
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
			foreach ($settings['blacklist'] as $character) {
				if (stripos($website, $character) !== false) {
					--$points;
				}
			}

			foreach ($settings['keywords'] as $keyword) {
				if (stripos($author, $keyword) !== false) {
					--$points;
				}

				if (stripos($content, $keyword) !== false) {
					--$points;
				}
			}

			// Body starts with...
			// -10 points
			if ($pos = stripos($content, ' ')) {
				$firstWord = mb_substr($content, 0, $pos);
			} else {
				$firstWord = trim($content);
			}

			$firstDisallow = array('interesting', 'cool', 'sorry') + $settings['keywords'];

			if (in_array(mb_strtolower($firstWord), $firstDisallow)) {
				$points = $points - 10;
			}

			// Author name has http:// in it
			// -2 points
			if (stripos($author, 'http://') !== false) {
				$points = $points - 2;
			}

			// Body used in previous comment
			// -1 per exact comment
			$previousComments = $model->find('count', array(
				'conditions' => array($columnMap['content'] => $content),
				'recursive' => -1,
				'contain' => false
			));

			if ($previousComments > 0) {
				$points = $points - $previousComments;
			}

			// Random character match
			// -1 point per 5 consecutive consonants
			preg_match_all('/[^aAeEiIoOuU\s]{5,}+/i', $content, $matches);
			$totalConsonants = count($matches[0]);

			if ($totalConsonants > 0) {
				$points = $points - $totalConsonants;
			}
		}

		// Finalize and save
		if ($points >= 1) {
			$status = $statusMap['approved'];
		} else if ($points == 0) {
			$status = $statusMap['pending'];
		} else if ($points <= $settings['deletion']) {
			$status = $statusMap['deleted'];
		} else {
			$status = $statusMap['spam'];
		}

		if ($status == $statusMap['deleted']) {
			$model->validationErrors[$columnMap['content']] = $settings['blockMessage'];

			return false;

		} else {
			$model->data[$model->alias][$columnMap['status']] = $status;

			if ($settings['savePoints']) {
				$model->data[$model->alias][$columnMap['points']] = $points;
			}

			if ($settings['sendEmail']) {
				$this->notify($model, $data, $status, $points);
			}
		}

		return true;
	}

	/**
	 * Sends out an email notifying you of a new comment.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $data
	 * @param array $status
	 * @param int $points
	 * @return void
	 */
	public function notify(Model $model, $data, $status, $points = 0) {
		$settings = $this->settings[$model->alias];
		$columnMap = $settings['columnMap'];

		if ($settings['model'] && $settings['link'] && $settings['email']) {
			$fields = array($columnMap['id'], $columnMap['title']);

			if ($settings['useSlug']) {
				$fields[] = $columnMap['slug'];
			}

			// Get result from foreign model
			$article = ClassRegistry::init($settings['model']);
			$result = $article->find('first', array(
				'fields' => $fields,
				'conditions' => array($columnMap['id'] => $data[$columnMap['foreignKey']]),
				'recursive' => -1,
				'contain' => false
			));

			// Format the link
			$link = str_replace('{id}', $result[$article->alias][$columnMap['id']], $settings['link']);

			if ($settings['useSlug']) {
				$link = str_replace('{slug}', $result[$article->alias][$columnMap['slug']], $settings['link']);
			}

			// Build message
			$title = $result[$article->alias][$columnMap['title']];
			$statuses = array_flip($settings['statusMap']);

			$message  = sprintf("A new comment has been posted for: %s\n\n", $link);
			$message .= sprintf("Name: %s <%s>\n", $data[$columnMap['author']], $data[$columnMap['email']]);
			$message .= sprintf("Status: %s (%s points)\n", $statuses[$status], $points);
			$message .= sprintf("Message:\n\n%s", $data[$columnMap['content']]);

			// Send email
			mail($settings['email'], 'Comment Approval: ' . $title, $message, 'From: ' . $data[$columnMap['author']] . ' <' . $data[$columnMap['email']] . '>');
		}
	}

}
