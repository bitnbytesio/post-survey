<h2>
    <a href="<?php echo admin_url("post.php?post={$post_id}&action=edit") ?>"><?php echo get_the_title($post_id) ?></a>
</h2>
<div class="clear"></div>
<form method="get">
    <input name="page" value="post-survey-feedback" type="hidden">
    <input name="post" value="<?php echo $_GET['post'] ?>" type="hidden">
    <input name="paged" value="<?php echo $page ?>" type="hidden">
    <div class="tablenav top">
        <div class="alignleft actions">
            <input type="text" name="from_date" class="datepicker" placeholder="From date">
            <input type="text" name="to_date" class="datepicker" placeholder="To date">
            <input class="button" type="submit" value="Filter">
        </div>
    </div>
</form>
<div class="clear"></div>
<?php if (!empty($results)) : ?>
    <table class="wp-list-table widefat fixed striped posts">

        <thead>
        <tr>
            <th>User</th>
            <th>Comment</th>
            <th>Review Type</th>
            <th>Date</th>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($results as $row) : ?>
            <?php
            if (!$user = get_the_author_meta('nicename', $row->user_id)) {
                $user = 'Anonymous';
            }
            ?>
            <tr>
                <td><?php echo $user ?></td>
                <td><?php echo $row->comment ?></td>
                <td><?php echo ($row->mood == 1) ? '<i class="post-survey-up post-survey-up-colored"></i>' : '<i class="post-survey-up post-survey-down-colored"></i>'; ?></td>
                <td><?php echo human_time_diff(strtotime($row->created_at), current_time('timestamp')); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>

    </table>

    <div class="clear"></div>
    <?php if ($total_pages > 1) : ?>
        <?php echo paginate_links(array(
            'base' => admin_url("admin.php?page=post-survey-feedback&post=$post_id%_%"),
            'format' => '&paged=%#%',
            'current' => $page,
            'show_all' => false,
            'total' => $total_pages,
            //'mid_size' => 4,
            'type' => 'list'
        ));
        ?>
    <?php endif; ?>
<?php else: ?>
    No feedback for this post!
<?php endif; ?>

<script>
    jQuery( function($) {
        $( ".datepicker" ).datepicker();
        $( ".datepicker" ).datepicker( "option", "dateFormat", "yy-mm-dd" );
    } );
</script>
