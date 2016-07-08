<style type="text/css">
    .brute-force-login-protection .status-yes {
        color: #27ae60;
    }

    .brute-force-login-protection .status-no {
        color: #cd3d2e;
    }

    .brute-force-login-protection .postbox-footer {
        padding: 10px;
        clear: both;
        border-top: 1px solid #ddd;
        background: #f5f5f5;
    }

    .brute-force-login-protection input[type="number"] {
        width: 60px;
    }

    .brute-force-login-protection tr.even {
        background-color: #f5f5f5;
    }
</style>

<script type="text/javascript">
    function resetOptions() {
        if (confirm("<?php _e('Are you sure you want to reset all options?', 'brute-force-login-protection'); ?>")) {
            document.forms["reset_form"].submit();
        }
    }

    function whitelistCurrentIP() {
        document.forms["whitelist_current_ip_form"].submit();
    }
</script>

<div class="wrap brute-force-login-protection">
    <h2><?php _e('Brute Force Login Protection Settings', 'brute-force-login-protection'); ?></h2>

    <div class="metabox-holder">
        <div class="postbox">
            <h3>
                <?php _e('Status', 'brute-force-login-protection'); ?>
                <?php if (in_array(false, $status)): ?>
                    <span class="dashicons dashicons-no status-no"></span>
                    <small class="status-no"><?php _e('You are not protected!', 'brute-force-login-protection'); ?></small>
                <?php else: ?>
                    <span class="dashicons dashicons-yes status-yes"></span>
                    <small class="status-yes"><?php _e('You are protected!', 'brute-force-login-protection'); ?></small>
                <?php endif; ?>
            </h3>
            <div class="inside">
                <?php if ($status['exists']): ?>
                    <span class="dashicons dashicons-yes status-yes"></span>
                    <strong><?php _e('.htaccess file found', 'brute-force-login-protection'); ?></strong>
                <?php else: ?>
                    <span class="dashicons dashicons-no status-no"></span>
                    <strong><?php _e('.htaccess file not found', 'brute-force-login-protection'); ?></strong>
                <?php endif; ?>
                <br/>
                <?php if ($status['readable']): ?>
                    <span class="dashicons dashicons-yes status-yes"></span>
                    <strong><?php _e('.htaccess file readable', 'brute-force-login-protection'); ?></strong>
                <?php else: ?>
                    <span class="dashicons dashicons-no status-no"></span>
                    <strong><?php _e('.htaccess file not readable', 'brute-force-login-protection'); ?></strong>
                <?php endif; ?>
                <br/>
                <?php if ($status['writable']): ?>
                    <span class="dashicons dashicons-yes status-yes"></span>
                    <strong><?php _e('.htaccess file writeable', 'brute-force-login-protection'); ?></strong>
                <?php else: ?>
                    <span class="dashicons dashicons-no status-no"></span>
                    <strong><?php _e('.htaccess file not writeable', 'brute-force-login-protection'); ?></strong>
                <?php endif; ?>
            </div>
        </div>

        <div class="postbox">
            <h3><?php _e('Options', 'brute-force-login-protection'); ?></h3>
            <form method="post" action="">
                <div class="inside">
                    <p>
                        <strong><?php _e('Allowed login attempts before blocking IP', 'brute-force-login-protection'); ?></strong>
                    </p>
                    <p>
                        <input type="number" min="1" max="100" name="allowed_attempts" value="<?php echo $settings['allowed_attempts']; ?>"/>
                    </p>

                    <p>
                        <strong><?php _e('Minutes before resetting login attempts count', 'brute-force-login-protection'); ?></strong>
                    </p>
                    <p>
                        <input type="number" min="1" name="reset_time" value="<?php echo $settings['reset_time']; ?>"/>
                    </p>

                    <p>
                        <strong><?php _e('Delay in seconds when a login attempt has failed (to slow down brute force attack)', 'brute-force-login-protection'); ?></strong>
                    </p>
                    <p>
                        <input type="number" min="1" max="10" name="login_failed_delay" value="<?php echo $settings['login_failed_delay']; ?>"/>
                    </p>

                    <p>
                        <strong><?php _e('Inform user about remaining login attempts on login page', 'brute-force-login-protection'); ?></strong>
                    </p>
                    <p>
                        <input type="checkbox" name="inform_user" value="true" <?php echo ($settings['inform_user']) ? 'checked' : ''; ?> />
                    </p>

                    <p>
                        <strong><?php _e('Send email to administrator when an IP has been blocked (don\'t use this on a slow server)', 'brute-force-login-protection'); ?></strong>
                    </p>
                    <p>
                        <input type="checkbox" name="send_email" value="true" <?php echo ($settings['send_email']) ? 'checked' : ''; ?> />
                    </p>

                    <p>
                        <strong><?php _e('Message to show to blocked users (leave empty for default message)', 'brute-force-login-protection'); ?></strong>
                    </p>
                    <p>
                        <input type="text" size="70" name="403_message" value="<?php echo $settings['403_message']; ?>"/>
                    </p>

                    <p><strong><?php _e('.htaccess file location', 'brute-force-login-protection'); ?></strong></p>
                    <p>
                        <input type="text" size="70" name="htaccess_dir" value="<?php echo $settings['htaccess_dir']; ?>"/>
                    </p>
                </div>
                <div class="postbox-footer">
                    <?php submit_button(__('Save', 'brute-force-login-protection'), 'primary', 'submit', false); ?>
                    &nbsp;
                    <a href="javascript:resetOptions()" class="button"><?php _e('Reset', 'brute-force-login-protection'); ?></a>
                </div>
            </form>
        </div>
    </div>

    <h3><?php _e('Blocked IPs', 'brute-force-login-protection'); ?></h3>
    <table class="wp-list-table widefat fixed">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="30%"><?php _e('Address', 'brute-force-login-protection'); ?></th>
                <th width="65%"><?php _e('Actions', 'brute-force-login-protection'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            foreach ($blockedIPs as $blockedIP):
                ?>
                <tr <?php echo ($i % 2 == 0) ? 'class="even"' : ''; ?>>
                    <td><?php echo $i; ?></td>
                    <td><strong><?php echo $blockedIP ?></strong></td>
                    <td>
                        <form method="post" action="">
                            <input type="hidden" name="IP" value="<?php echo $blockedIP ?>"/>
                            <input type="submit" name="unblock" value="<?php echo __('Unblock', 'brute-force-login-protection'); ?>" class="button"/>
                        </form>
                    </td>
                </tr>
                <?php
                $i++;
            endforeach;
            ?>
            <tr <?php echo ($i % 2 == 0) ? 'class="even"' : ''; ?>>
                <td><?php echo $i; ?></td>
                <form method="post" action="">
                    <td>
                        <input type="text" name="IP" placeholder="<?php _e('IP to block', 'brute-force-login-protection'); ?>" required/>
                    </td>
                    <td>
                        <input type="submit" name="block" value="<?php _e('Manually block IP', 'brute-force-login-protection'); ?>" class="button button-primary"/>
                    </td>
                </form>
            </tr>
        </tbody>
    </table>

    <h3><?php _e('Whitelisted IPs', 'brute-force-login-protection'); ?></h3>
    <table class="wp-list-table widefat fixed">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="30%"><?php _e('Address', 'brute-force-login-protection'); ?></th>
                <th width="65%"><?php _e('Actions', 'brute-force-login-protection'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            foreach ($whitelistedIPs as $whitelistedIP):
                ?>
                <tr <?php echo ($i % 2 == 0) ? 'class="even"' : ''; ?>>
                    <td><?php echo $i; ?></td>
                    <td><strong><?php echo $whitelistedIP ?></strong></td>
                    <td>
                        <form method="post" action="">
                            <input type="hidden" name="IP" value="<?php echo $whitelistedIP ?>"/>
                            <input type="submit" name="unwhitelist" value="<?php echo __('Remove from whitelist', 'brute-force-login-protection'); ?>" class="button"/>
                        </form>
                    </td>
                </tr>
                <?php
                $i++;
            endforeach;
            ?>
            <tr <?php echo ($i % 2 == 0) ? 'class="even"' : ''; ?>>
                <td><?php echo $i; ?></td>
                <form method="post" action="">
                    <td>
                        <input type="text" name="IP" placeholder="<?php _e('IP to whitelist', 'brute-force-login-protection'); ?>" required/>
                    </td>
                    <td>
                        <input type="submit" name="whitelist" value="<?php _e('Add to whitelist', 'brute-force-login-protection'); ?>" class="button button-primary"/>
                        <?php if (!in_array($currentIP, $whitelist)): ?>
                            &nbsp;
                            <a href="javascript:whitelistCurrentIP()" class="button"><?php printf(__('Whitelist my current IP (%s)', 'brute-force-login-protection'), $currentIP); ?></a>
                        <?php endif; ?>
                    </td>
                </form>
            </tr>
        </tbody>
    </table>

    <form id="reset_form" method="post" action="">
        <input type="hidden" name="reset" value="true"/>
    </form>

    <form id="whitelist_current_ip_form" method="post" action="">
        <input type="hidden" name="whitelist" value="true"/>
        <input type="hidden" name="IP" value="<?php echo $currentIP; ?>"/>
    </form>
</div>
