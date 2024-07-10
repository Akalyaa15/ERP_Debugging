<?php foreach ($activity_logs as $log) : ?>
    <div class="media b-b mb15">
        <div class="media-left">
            <span class="avatar avatar-xs">
                <img src="<?= get_avatar($log->created_by_avatar); ?>" alt="..." />
            </span>
        </div>
        <div class="media-body">
            <div class="media-heading">
                <?php if ($log->user_type === "staff") : ?>
                    <?= get_team_member_profile_link($log->created_by, $log->created_by_user, ['class' => 'dark strong']); ?>
                <?php elseif ($log->user_type === "resource") : ?>
                    <?= get_rm_member_profile_link($log->created_by, $log->created_by_user, ['class' => 'dark strong']); ?>
                <?php else : ?>
                    <?= get_client_contact_profile_link($log->created_by, $log->created_by_user, ['class' => 'dark strong']); ?>
                <?php endif; ?>
                <small><span class="text-off"><?= format_to_relative_time($log->created_at); ?></span></small>
            </div>
            <p>
                <?php
                $label_class = 'default';
                if ($log->action === "created") {
                    $label_class = "success";
                    $log->action = "added";
                } elseif ($log->action === "updated") {
                    $label_class = "warning";
                } elseif ($log->action === "deleted") {
                    $label_class = "danger";
                }
                ?>
                <span class="label label-<?= $label_class; ?>"><?= lang($log->action); ?></span>
                <span>
                    <?php if ($log->log_type === "project_file") : ?>
                        <?= lang($log->log_type) . ": " . remove_file_prefix(convert_mentions($log->log_type_title)); ?>
                    <?php elseif ($log->log_type === "task") : ?>
                        <?= lang($log->log_type) . ": " . modal_anchor(get_uri("projects/task_view"), " #" . $log->log_type_id . " - " . convert_mentions($log->log_type_title), ['title' => lang('task_info') . " #$log->log_type_id", 'class' => 'dark', 'data-post-id' => $log->log_type_id]); ?>
                    <?php else : ?>
                        <?= lang($log->log_type) . ": " . convert_mentions($log->log_type_title); ?>
                    <?php endif; ?>
                </span>
                <?php if ($log->action === "updated" && $log->changes !== "") : ?>
                    <ul>
                        <?php foreach (unserialize($log->changes) as $field => $value) : ?>
                            <li><?= get_change_logs($log->log_type, $field, $value); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </p>
            <?php if ($log->log_for2 && $log->log_for2 != "customer_feedback") : ?>
                <p>
                    <?php if ($log->log_for2 === "task") : ?>
                        <?= lang($log->log_for2) . ": " . modal_anchor(get_uri("projects/task_view"), " #" . $log->log_for_id2, ['title' => lang('task_info') . " #$log->log_for_id2", 'class' => 'dark', 'data-post-id' => $log->log_for_id2]); ?>
                    <?php else : ?>
                        <?= lang($log->log_for2) . ": #" . $log->log_for_id2; ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if (isset($log->log_for_title)) : ?>
                <p><?= lang($log->log_for) . ": " . anchor("projects/view/" . $log->log_for_id, $log->log_for_title, ['class' => 'dark']); ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<div id="<?= $next_container_id; ?>">
    <div class="text-center">
        <?php if ($result_remaining > 0) : ?>
            <?= ajax_anchor(route_to('projects/history', $next_page_offset, $log_for, $log_for_id, $log_type, $log_type_id), lang('load_more'), ['class' => 'btn btn-default b-a load-more mt15', 'title' => lang('load_more'), 'data-inline-loader' => '1', 'data-real-target' => '#' . $next_container_id]); ?>
        <?php endif; ?>
    </div>
</div>
