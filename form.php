<form action="options.php" method="post">
    <?php settings_fields('post-survey'); ?>
    <?php do_settings_sections('post-survey'); ?>


    <input name="Submit" type="submit" class="button button-primary" value="Save"/>
</form>
<div class="clear"></div>
<table width="100%" class="form-table">
    <tbody>
    <tr>
        <td>
            <h3>Default Template</h3>
        </td>
    </tr>
    <tr>
        <td>
            <p>Avilable variables: {count_positive},{count_negative}, {comment_field} </p>
        </td>
    </tr>
    <tr>
        <td>
            <textarea readonly style="width:100%; min-height:240px"><?php echo $this->default_templete; ?></textarea>
        </td>
    </tr>
    </tbody>
</table>

