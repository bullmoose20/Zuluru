<p>Dear <?php echo $person['first_name']; ?>,</p>
<p><?php echo $captain; ?> has invited you to join the roster of the <?php
echo Configure::read('organization.name'); ?> team <?php echo $team['name']; ?> as a <?php
echo Configure::read("options.roster_position.$position"); ?>.</p>
<p><?php echo $team['name']; ?> plays in the <?php echo $league['name']; ?> league, which operates on <?php
echo implode (' and ', Set::extract ('/Day/name', $league)); ?>.</p>
<p>More details about <?php echo $team['name']; ?> may be found at
<?php
$url = Router::url(array('controller' => 'teams', 'action' => 'view', 'team' => $team['id']), true);
echo $this->Html->link($url, $url);
?></p>
<p>We ask that you please accept or decline this invitation at your earliest convenience. The invitation will expire after a couple of weeks.</p>
<p>If you accept the invitation, you will be added to the team's roster and your contact information will be made available to the team captain.</p>
<p>Note that, before accepting the invitation, you must be a registered member of <?php echo Configure::read('organization.short_name'); ?>.</p>
<p>Accept the invitation here:
<?php
$url = Router::url(array('controller' => 'teams', 'action' => 'roster_accept', 'team' => $team['id'], 'person' => $person['id'], 'code' => $code), true);
echo $this->Html->link($url, $url);
?></p>
<p>If you decline the invitation you will be removed from this team's roster and your contact information will not be made available to the captain. This protocol is in accordance with the <?php
echo Configure::read('organization.short_name'); ?> Privacy Policy.</p>
<p>Decline the invitation here:
<?php
$url = Router::url(array('controller' => 'teams', 'action' => 'roster_decline', 'team' => $team['id'], 'person' => $person['id'], 'code' => $code), true);
echo $this->Html->link($url, $url);
?></p>
<p>Please be advised that players are NOT considered a part of a team roster until they have accepted a captain's invitation to join. The <?php
echo $team['name']; ?> roster must be completed (minimum of <?php
echo Configure::read("roster_requirements.{$league['ratio']}"); ?> rostered players) by the team roster deadline (<?php
$date_format = array_shift (Configure::read('options.date_formats'));
echo $this->Time->format($date_format, $league['roster_deadline']);
?>), and all team members must have accepted the captain's invitation.</p>
<p>Thanks,
<br /><?php echo Configure::read('email.admin_name'); ?>
<br /><?php echo Configure::read('organization.short_name'); ?> web team</p>
