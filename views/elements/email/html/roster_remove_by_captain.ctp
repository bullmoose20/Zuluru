<p>Dear <?php echo $person['first_name']; ?>,</p>
<p><?php echo $captain; ?> has removed you from the roster of the <?php
echo Configure::read('organization.name'); ?> team <?php echo $team['name']; ?> You were previously listed as a <?php
echo Configure::read("options.roster_position.$old_position"); ?>.</p>
<p>This is a notification only, there is no action required on your part.</p>
<p>If you believe that this has happened in error, please contact <?php echo $reply; ?>.</p>
<p>Thanks,
<br /><?php echo Configure::read('email.admin_name'); ?>
<br /><?php echo Configure::read('organization.short_name'); ?> web team</p>
