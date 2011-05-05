<p>Dear <?php echo $person['first_name']; ?>,</p>
<p>You have not yet indicated your attendance for the <?php
echo $team['name']; ?> game against <?php echo $opponent['name']; ?> at <?php
echo $this->ZuluruTime->time($game['GameSlot']['game_start']); ?> on <?php
echo $this->ZuluruTime->date($game['GameSlot']['game_date']);
?>.</p>
<?php if (!in_array($person['TeamsPerson']['position'], Configure::read('playing_roster_positions'))): ?>
<p>The captain has invited you to play in this game. However, when teams are short, captains will often invite a number of people to fill in, so it's possible that even if you confirm now, you might be uninvited later if others responded first. You will receive another email from the system in this case, but you may want to double-check with your captain that you are needed before the game.</p>
<?php endif; ?>
<p>If you are able to play:
<?php
$url = Router::url(array('controller' => 'games', 'action' => 'attendance_change', 'team' => $team['id'], 'game' => $game['Game']['id'], 'person' => $person['id'], 'code' => $code, 'status' => ATTENDANCE_ATTENDING), true);
echo $this->Html->link($url, $url);
?></p>
<p>If you are unavailable to play:
<?php
$url = Router::url(array('controller' => 'games', 'action' => 'attendance_change', 'team' => $team['id'], 'game' => $game['Game']['id'], 'person' => $person['id'], 'code' => $code, 'status' => ATTENDANCE_ABSENT), true);
echo $this->Html->link($url, $url);
?></p>
<p>Thanks,
<br /><?php echo Configure::read('email.admin_name'); ?>
<br /><?php echo Configure::read('organization.short_name'); ?> web team</p>