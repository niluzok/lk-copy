<?php

declare(strict_types=1);

?>

<style>
    .action-bar {
        position: fixed;
        top: 0;
        width: 100%;
        /* background-color: #f8f9fa; */
        /* border-bottom: 1px solid #ddd; */
        padding: 10px;
        z-index: 1000;

        visibility: hidden;
        opacity: 0;
        transition: visibility 0.15s, opacity 0.15s ease-in-out;

    }

    .action-bar.show {
        visibility: visible;
        opacity: 1;
    }

    .action-bar .btn {
        margin-bottom: 0;
    }
    
</style>

<script type="module">

    import SelectedRowsActionBar from '/js/es-modules/SelectedRowsActionBar.js';
    
    SelectedRowsActionBar.initOnPageLoad({
        checkboxesContainerSelector: '.grid-view',
        actionBarSelector: '.action-bar'
    });

</script>
