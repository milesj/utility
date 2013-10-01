<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');
App::uses('CakeEmail', 'Network/Email');

/**
 * A CakePHP Behavior that moderates and validates comments to check for spam.
 * Validation is based on a point system where high points equal an automatic approval and low points are marked as spam or deleted.
 *
 * {{{
 *      Example model schema:
 *
 *      CREATE TABLE `comments` (
 *         `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *         `article_id` INT NOT NULL,
 *         `status` SMALLINT NOT NULL DEFAULT 0,
 *         `points` INT NOT NULL,
 *         `name` VARCHAR(50) NOT NULL,
 *         `email` VARCHAR(50) NOT NULL,
 *         `website` VARCHAR(50) NOT NULL,
 *         `ip` VARCHAR(50) NOT NULL,
 *         `content` TEXT NOT NULL,
 *         `created` DATETIME NULL DEFAULT NULL,
 *         `modified` DATETIME NULL DEFAULT NULL,
 *         INDEX (`article_id`)
 *     ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
 * }}}
 */
class SpamBlockerBehavior extends ModelBehavior {

    /**
     * Default settings.
     *
     * @type array {
     *      @type string $model         Model name of the parent article
     *      @type string $link          Link to the parent article (:id and :slug will be replaced with field data)
     *      @type string $email         Email address where the notify emails should go
     *      @type bool $useSlug         If you want to use a slug instead of id
     *      @type bool $savePoints      Should the points be saved to the database?
     *      @type bool $sendEmail       Should you receive a notification email for each comment?
     *      @type int $deletion         Amount of points till the comment is deleted (negative)
     *      @type string $blockMessage  Error message received when comment falls below threshold
     *      @type array $keywords       List of blacklisted keywords within the author and content
     *      @type array $blacklist      List of blacklisted characters within the website and URLs
     *      @type array $columnMap      Names for table columns within the comments table and the parent
     *      @type array $statusMap      Status codes for the enum (or integer) status column
     * }
     */
    protected $_defaults = array(
        'model' => 'article',
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
            'email', 'click', 'unsubscribe', 'buy', 'sell', 'sales', 'earn', 'cheap', 'sale'
        ),
        'blacklist' => array('.html', '.info', '.de', '.pl', '.cn', '.ru', '.biz'),
        'columnMap' => array(
            'foreignKey'    => 'article_id',
            'id'            => 'id',
            'author'        => 'name',
            'content'       => 'content',
            'email'         => 'email',
            'website'       => 'website',
            'slug'          => 'slug',
            'title'         => 'title',
            'status'        => 'status',
            'points'        => 'points',
            'ip'            => 'ip'
        ),
        'statusMap' => array(
            'pending'   => 0,
            'approved'  => 1,
            'deleted'   => 2,
            'spam'      => 3
        )
    );

    /**
     * Merge settings.
     *
     * @param Model $model
     * @param array $settings
     */
    public function setup(Model $model, $settings = array()) {
        $this->settings[$model->alias] = Hash::merge($this->_defaults, $settings);
    }

    /**
     * Runs before a save and marks the content as spam or regular comment.
     *
     * @param Model $model
     * @param array $options
     * @return bool
     */
    public function beforeSave(Model $model, $options = array()) {
        $settings = $this->settings[$model->alias];
        $columnMap = $settings['columnMap'];
        $statusMap = $settings['statusMap'];

        $data = $model->data[$model->alias];
        $points = 0;

        // Updating record, so leave it alone
        if (!empty($data[$model->primaryKey]) || $model->id) {
            return true;
        }

        if ($data) {
            $website = $data[$columnMap['website']];
            $content = $data[$columnMap['content']];
            $author = $data[$columnMap['author']];
            $email = $data[$columnMap['email']];

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
                    if (in_array($comment[$model->alias][$columnMap['status']], array($statusMap['spam'], $statusMap['deleted']))) {
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
                if (mb_stripos($content, $keyword) !== false) {
                    --$points;
                }
            }

            // URLs that have certain words or characters in them
            // -1 per blacklisted word
            // URL length
            // -1 if more then 30 chars
            foreach ($links as $link) {
                foreach ($settings['blacklist'] as $character) {
                    if (mb_stripos($link, $character) !== false) {
                        --$points;
                    }
                }

                foreach ($settings['keywords'] as $keyword) {
                    if (mb_stripos($link, $keyword) !== false) {
                        --$points;
                    }
                }

                if (mb_strlen($link) >= 30) {
                    --$points;
                }
            }

            // Check the website, author and comment for blacklisted
            // -1 per instance
            foreach ($settings['blacklist'] as $character) {
                if (mb_stripos($website, $character) !== false) {
                    --$points;
                }
            }

            foreach ($settings['keywords'] as $keyword) {
                if (mb_stripos($author, $keyword) !== false) {
                    --$points;
                }

                if (mb_stripos($content, $keyword) !== false) {
                    --$points;
                }
            }

            // Body starts with...
            // -10 points
            if ($pos = mb_stripos($content, ' ')) {
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
            if (mb_stripos($author, 'http://') !== false) {
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
            $totalMatches = preg_match_all('/[^aAeEiIoOuU\s]{5,}+/i', $content);

            if ($totalMatches > 0) {
                $points = $points - $totalMatches;
            }

            // Email mostly consonants
            // -5 points
            if (preg_match('/[^aAeEiIoOuU\s]{5,}+/i', strstr($email, '@', true))) {
                $points = $points - 5;
            }

            // IP has been marked as spam before
            // -1 point per filtered IP
            if (!empty($data[$columnMap['ip']])) {
                $blockedIPs = $model->find('count', array(
                    'conditions' => array(
                        $columnMap['ip'] => $data[$columnMap['ip']],
                        $columnMap['status'] => array($statusMap['spam'], $statusMap['deleted'])
                    ),
                    'recursive' => -1,
                    'contain' => false
                ));

                if ($blockedIPs > 0) {
                    $points = $points - $blockedIPs;
                }
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
     * @param Model $model
     * @param array $data
     * @param int $status
     * @param int $points
     */
    public function notify(Model $model, $data, $status, $points = 0) {
        $settings = $this->settings[$model->alias];
        $columnMap = $settings['columnMap'];

        if ($settings['model'] && $settings['link'] && $settings['email']) {
            $Article = ClassRegistry::init(Inflector::classify($settings['model']));

            $result = $Article->find('first', array(
                'conditions' => array($columnMap['id'] => $data[$columnMap['foreignKey']]),
                'recursive' => -1,
                'contain' => false
            ));

            // Build variables
            $link = str_replace('{id}', $result[$Article->alias][$columnMap['id']], $settings['link']);

            if ($settings['useSlug']) {
                $link = str_replace('{slug}', $result[$Article->alias][$columnMap['slug']], $settings['link']);
            }

            $title = $result[$Article->alias][$columnMap['title']];
            $email = $data[$columnMap['email']];
            $author = $data[$columnMap['author']];

            // Send email
            $Email = new CakeEmail();
            $Email
                ->to($settings['email'])
                ->from(array($email => $author))
                ->subject('Comment Approval: ' . $title)
                ->helpers(array('Html', 'Time'))
                ->viewVars(array(
                    'settings' => $settings,
                    'article' => $result,
                    'comment' => $data,
                    'link' => $link,
                    'status' => $status,
                    'points' => $points
                ));

            if (Configure::read('debug')) {
                $Email->transport('Debug')->config(array('log' => true));
            }

            // Use a custom template
            if (is_string($settings['sendEmail'])) {
                $Email
                    ->template($settings['sendEmail'])
                    ->emailFormat('both')
                    ->send();

            // Send a simple message
            } else {
                $statuses = array_flip($settings['statusMap']);

                $message  = sprintf("A new comment has been posted for: %s\n\n", $link);
                $message .= sprintf("Name: %s <%s>\n", $author, $email);
                $message .= sprintf("Status: %s (%s points)\n", $statuses[$status], $points);
                $message .= sprintf("Message:\n\n%s", $data[$columnMap['content']]);

                $Email->send($message);
            }
        }
    }

}
