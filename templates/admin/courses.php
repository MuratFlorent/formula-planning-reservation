<?php
// Prevent direct access
if (!defined('ABSPATH')) exit;

// Get all courses
$courses = \FPR\Modules\CourseHandler::get_all_courses();

// Get all subscriptions with courses
global $wpdb;
$subscriptions_with_courses = $wpdb->get_results("
    SELECT DISTINCT cs.* 
    FROM {$wpdb->prefix}fpr_customer_subscriptions cs
    JOIN {$wpdb->prefix}fpr_subscription_courses sc ON cs.id = sc.subscription_id
    WHERE cs.status = 'active'
    ORDER BY cs.id DESC
");

?>
<div class="wrap">
    <h1>Gestion des Cours</h1>
    
    <div class="notice notice-info">
        <p>Cette page vous permet de gérer les cours et de les importer dans Amelia si nécessaire.</p>
    </div>
    
    <h2>Liste des Cours</h2>
    
    <?php if (empty($courses)) : ?>
        <p>Aucun cours n'a été trouvé. Les cours seront créés automatiquement lorsque les clients passeront des commandes.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Durée</th>
                    <th>Instructeur</th>
                    <th>Horaire</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course) : ?>
                    <tr>
                        <td><?php echo esc_html($course->id); ?></td>
                        <td><?php echo esc_html($course->name); ?></td>
                        <td><?php echo esc_html($course->duration); ?></td>
                        <td><?php echo esc_html($course->instructor); ?></td>
                        <td>
                            <?php if ($course->start_time && $course->end_time) : ?>
                                <?php echo esc_html($course->start_time . ' - ' . $course->end_time); ?>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($course->status); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <h2 style="margin-top: 30px;">Abonnements avec Cours</h2>
    
    <?php if (empty($subscriptions_with_courses)) : ?>
        <p>Aucun abonnement avec des cours n'a été trouvé.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Utilisateur</th>
                    <th>Commande</th>
                    <th>Saison</th>
                    <th>Cours</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscriptions_with_courses as $subscription) : 
                    // Get user info
                    $user = get_userdata($subscription->user_id);
                    if (!$user) continue;
                    
                    // Get saison info
                    $saison = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE id = %d",
                        $subscription->saison_id
                    ));
                    
                    // Get courses for this subscription
                    $sub_courses = \FPR\Modules\CourseHandler::get_subscription_courses($subscription->id);
                    ?>
                    <tr>
                        <td><?php echo esc_html($subscription->id); ?></td>
                        <td>
                            <?php echo esc_html($user->display_name); ?><br>
                            <small><?php echo esc_html($user->user_email); ?></small>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $subscription->order_id . '&action=edit')); ?>" target="_blank">
                                #<?php echo esc_html($subscription->order_id); ?>
                            </a>
                        </td>
                        <td><?php echo $saison ? esc_html($saison->name) : '-'; ?></td>
                        <td>
                            <?php if (!empty($sub_courses)) : ?>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <?php foreach ($sub_courses as $course) : ?>
                                        <li><?php echo esc_html($course->name); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button import-to-amelia" data-subscription-id="<?php echo esc_attr($subscription->id); ?>">
                                Importer dans Amelia
                            </button>
                            <span class="spinner" style="float: none; margin: 0 0 0 5px;"></span>
                            <div class="import-result"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('.import-to-amelia').on('click', function() {
        var button = $(this);
        var spinner = button.next('.spinner');
        var resultDiv = button.siblings('.import-result');
        var subscriptionId = button.data('subscription-id');
        
        // Disable button and show spinner
        button.prop('disabled', true);
        spinner.addClass('is-active');
        resultDiv.html('');
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fpr_import_courses_to_amelia',
                subscription_id: subscriptionId,
                nonce: '<?php echo wp_create_nonce('fpr_import_courses'); ?>'
            },
            success: function(response) {
                spinner.removeClass('is-active');
                
                if (response.success) {
                    var html = '<div class="notice notice-success inline"><p>Import réussi !</p>';
                    
                    if (response.data.results && response.data.results.length > 0) {
                        html += '<ul>';
                        $.each(response.data.results, function(i, result) {
                            var statusClass = result.success ? 'success' : 'error';
                            html += '<li class="' + statusClass + '">' + result.course + ': ' + result.message + '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    html += '</div>';
                    resultDiv.html(html);
                } else {
                    resultDiv.html('<div class="notice notice-error inline"><p>Erreur: ' + response.data + '</p></div>');
                }
                
                // Re-enable button after a delay
                setTimeout(function() {
                    button.prop('disabled', false);
                }, 2000);
            },
            error: function() {
                spinner.removeClass('is-active');
                resultDiv.html('<div class="notice notice-error inline"><p>Erreur de communication avec le serveur</p></div>');
                button.prop('disabled', false);
            }
        });
    });
});
</script>

<style>
.import-result {
    margin-top: 10px;
}
.import-result .notice {
    margin: 5px 0;
    padding: 5px 10px;
}
.import-result ul {
    margin: 5px 0 5px 20px;
}
.import-result .success {
    color: green;
}
.import-result .error {
    color: red;
}
</style>