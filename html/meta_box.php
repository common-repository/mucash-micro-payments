<?php wp_nonce_field("mucash_meta", "mucash_meta_nonce"); ?>
<label for="mucash_price">Price</label>
<select name="mucash_price">
  <?php
      $current = get_post_meta($data->ID, "mucash_price", true);
      echo "<option value=\"0\">(Unlocked)</option>";
      foreach (array(1, 2, 5, 10, 15, 20, 25, 50, 75, 99) as $price) {
          $cur = MuCashCurrency::fromCents($price);
          $label = (string)$cur;
          $intval = $cur->getEncoded();
          if ($current == $intval) {
              $sel = 'selected="selected"';
          } else {
              $sel = "";
          }
          echo "<option $sel value=\"$intval\">$label</option>";
      }
  ?>
</select>
<p>When locked, MuCash will display the excerpt in place of the full
article.  Use the "Insert More Tag" button to customize the excerpt.</p>
