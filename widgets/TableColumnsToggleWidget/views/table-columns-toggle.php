<?php

declare(strict_types=1);

?>

<?php if ($useDefaultStyles): ?>
<style>

    .gridview-wrapper {
        position: relative;
    }

    .table-column-toggle-btn {
        position: absolute;
        right: 0;
        z-index: 2;
        top: -20px;
    }

    .table-column-toggle-btn .glyphicon {
        top: 3px;
    }

    .table-column-toggle-btn button {
        border: none;
        background: none;
    }

</style>
<?php endif ?>

<script type="module">

    import ColumnToggle from '/js/es-modules/table-column-toggle.js';

    ColumnToggle.initOnPageLoad({
        tableSelector: '<?= $tableSelector ?>',
        checkboxContainerSelector: '<?= $checkboxContainerSelector ?>',
        storageKey: '<?= $storageKey ?>',
        defaultHideColumnsIds: <?= json_encode($defaultHideColumnsIds) ?>,
    });

</script>

<div class="btn-group table-column-toggle-btn">
    <button type="button" style="font-size: 1.5rem;" class="btn btn-md btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="glyphicon glyphicon-cog"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-right" id="columns-dropdown">
        <!-- Элементы будут добавлены через JavaScript -->
    </ul>
</div>
