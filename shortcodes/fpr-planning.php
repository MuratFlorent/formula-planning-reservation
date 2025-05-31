<?php

namespace FPR\Shortcodes;

function render_planning_shortcode() {
	ob_start();
	?>
    <div class="fpr-planning-wrapper">
    </div>
    <div id="fpr-toast" style="visibility: hidden;">Vos cours ont bien été enregistrés !</div>

	<?php
	return ob_get_clean();
}

add_shortcode('fpr_planning', __NAMESPACE__ . '\\render_planning_shortcode');
