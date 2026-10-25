<div class="mt-4 text-center" style="font-size:0.85rem;color:#6c757d;">
    Last Updated:
    <?=
!empty($profileUser['updated_at'])
? date('F d, Y h:i A', strtotime($profileUser['updated_at']))
: 'Never';
    ?>
</div>