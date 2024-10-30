<div class="wrap">
<h2>MuCash Settings</h2>
<p>Don't have a MuCash account yet?  <a target="_blank" href="<?php echo MUCASH_URL; ?>/account/signup?onsuccess=%2Faccount%2Fsites%2Fadd">Sign up here first</a>.</p>
<form method="post" action="options.php">
<?php settings_fields(MuCashWP::S_SECTION_MAIN); ?>
<h3>General Settings</h3>
<table class="form-table">
<tr valign="top">
  <th scope="row">Site ID</th>
  <td><input type="text" name="mucash_merchant_id" value="<?php echo get_option(MuCashWP::S_MERCHANT_ID); ?>"/></td>
</tr>
<tr valign="top">
  <th scope="row">API Key</th>
  <td><input type="text" name="mucash_api_key" size="40" value="<?php echo get_option(MuCashWP::S_API_KEY); ?>"/></td>
</tr>
</table>
<h3>Donate Buttons</h3>
<p>This option adds a simple MuCash donate button at the end of posts and pages.  Donation
buttons will not be show on paid posts.</p>      
<table class="form-table">
  <tr valign="top">
    <th scope="row">Enable</th>
    <td>
      <input type="checkbox" name="<?php echo MuCashWP::S_DONATE_BUTTON?>" value="1" <?php if(get_option(MuCashWP::S_DONATE_BUTTON, 1)) echo 'checked="checked"'?>/>
    </td>
  </tr>
  <tr valign="top">
    <th scope="row">Appeal Text (HTML allowed)</th>
    <td>
      <textarea cols="80" rows="5" name="<?php echo MuCashWP::S_DONATE_APPEAL; ?>"><?php 
          $at = get_option(MucashWP::S_DONATE_APPEAL);
          echo ($at === false) ? MuCashWP::STR_DEFAULT_APPEAL : $at; 
      ?></textarea>
      <p class="description">
        In our experience, a brief explanation of why your readers should support
        your site leads to significantly higher engagement than merely putting
        up donation buttons.  This text will be shown at the end of posts with
        donate buttons.
      </p>
    </td>
  </tr>
</table>

<h3>Donations in Comments</h3>
<p>This option lets your users leave a donation along with their comments.  This
can work expecially well when you have highly engaged users who actively participate 
in discussion on your site and care about the community.</p>

<table class="form-table">
  <tr valign="top">
    <th scope="row">Enable</th>
    <td>
      <input type="checkbox" name="<?php echo MuCashWP::S_DONATE_COMMENT; ?>" value="1" <?php if(get_option(MuCashWP::S_DONATE_COMMENT, 1)) echo 'checked="checked"'?>/>
    </td>
  </tr>
  <tr valign="top">
    <th scope="row">Display Appeal Text</th>
    <td>
      <input type="checkbox" name="<?php echo MuCashWP::S_SHOW_COMMENT_APPEAL; ?>" value="1" <?php if(get_option(MuCashWP::S_SHOW_COMMENT_APPEAL, 1)) echo 'checked="checked"'?>/>
      <span class="description">Display the &ldquo;appeal&rdquo; text set above after the comment form (recommended)</span>
    </td>
  </tr>
</table>

<p class="submit">
<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>
</form>
</div>
