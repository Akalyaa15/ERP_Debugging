<div class="panel panel-default p15 no-border m0">
    <span><?= lang("status") . ": " . $work_order_status_label; ?></span>
    <span class="ml15">
        <?= lang("vendor") . ": "; ?>
        <?= anchor(get_uri("vendors/view/" . $work_order_info->vendor_id), $work_order_info->company_name); ?>
    </span>
    <span class="ml15">
        <?php if ($estimate_info->project_id): ?>
            <?= lang("project") . ": "; ?>
            <?= anchor(get_uri("projects/view/" . $estimate_info->project_id), $estimate_info->project_title); ?>
        <?php endif; ?>
    </span>
</div>
