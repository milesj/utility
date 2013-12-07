<blockquote class="decoda-quote">
    <?php if (!empty($author) || !empty($date)) { ?>
        <div class="decoda-quote-head">
            <?php if (!empty($date)) { ?>
                <span class="decoda-quote-date">
                    <?php echo $this->Time->format($dateFormat, $date); ?>
                </span>
            <?php }

            if (!empty($author)) { ?>
                <span class="decoda-quote-author">
                    <?php echo $filter->message('quoteBy', array('author' => h($author))); ?>
                </span>
            <?php } ?>

            <span class="clear"></span>
        </div>
    <?php } ?>

    <div class="decoda-quote-body">
        <?php echo $content; ?>
    </div>
</blockquote>
