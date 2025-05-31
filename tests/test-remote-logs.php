<?php
/**
 * Script de test pour l'accès aux logs à distance
 * 
 * Ce script montre comment utiliser l'API REST pour accéder aux logs à distance.
 * Pour l'exécuter, vous pouvez utiliser la commande suivante depuis le terminal:
 * 
 * php test-remote-logs.php
 */

// Configuration
$site_url = 'https://votre-site.com'; // Remplacez par l'URL de votre site
$endpoint = '/wp-json/fpr/v1/logs';
$token = ''; // Remplacez par le token affiché dans l'interface d'administration

// Si le token n'est pas défini, afficher un message d'erreur
if (empty($token)) {
    echo "ERREUR: Vous devez définir le token d'authentification.\n";
    echo "Vous pouvez le trouver dans l'interface d'administration sous FPR > Diagnostic.\n";
    exit(1);
}

// Construire l'URL complète
$url = $site_url . $endpoint;

// Paramètres de la requête
$params = [
    'type' => 'fpr', // Options: 'fpr', 'custom', 'php'
    'lines' => 20    // Nombre de lignes à récupérer (max 500)
];

// Ajouter les paramètres à l'URL
$url .= '?' . http_build_query($params);

echo "Récupération des logs depuis: $url\n\n";

// Initialiser cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-FPR-Token: ' . $token
]);

// Exécuter la requête
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Vérifier le code de réponse HTTP
if ($http_code !== 200) {
    echo "ERREUR: La requête a échoué avec le code HTTP $http_code\n";
    echo "Réponse: $response\n";
    exit(1);
}

// Décoder la réponse JSON
$data = json_decode($response, true);

if (!$data || !isset($data['success']) || $data['success'] !== true) {
    echo "ERREUR: La réponse n'est pas au format attendu\n";
    echo "Réponse: $response\n";
    exit(1);
}

// Afficher les informations sur les logs
echo "Type de log: {$data['log_type']}\n";
echo "Nombre de lignes: {$data['lines']}\n\n";
echo "CONTENU DES LOGS:\n";
echo "----------------\n";

// Afficher les logs
foreach ($data['logs'] as $line) {
    echo $line;
}

echo "\n\nTest terminé avec succès!\n";