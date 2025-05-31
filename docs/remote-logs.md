# Accès aux logs à distance

Cette fonctionnalité permet de lire les fichiers de logs du plugin Formula Planning Reservation à distance via une API REST sécurisée.

## Fonctionnalités

- Accès sécurisé aux logs via un token d'authentification
- Possibilité de consulter différents types de logs (FPR, custom, PHP)
- Limitation du nombre de lignes pour éviter les abus
- Interface d'administration pour visualiser le token d'API

## Comment utiliser l'API

### Endpoint

```
GET /wp-json/fpr/v1/logs
```

### Paramètres

| Paramètre | Description | Valeurs possibles | Défaut |
|-----------|-------------|-------------------|--------|
| type      | Type de log à récupérer | 'fpr', 'custom', 'php' | 'fpr' |
| lines     | Nombre de lignes à récupérer | 1-500 | 50 |

### En-têtes requis

```
X-FPR-Token: votre_token_ici
```

Le token d'authentification est disponible dans l'interface d'administration WordPress sous FPR > Diagnostic.

### Exemple de réponse

```json
{
  "success": true,
  "log_type": "fpr",
  "lines": 5,
  "logs": [
    "[2023-10-15 14:32:45] Initialisation du plugin\n",
    "[2023-10-15 14:33:12] Commande #123 créée\n",
    "[2023-10-15 14:33:15] [WooToAmelia] Order #123 status changed to processing\n",
    "[2023-10-15 14:33:16] [Amelia] Registering: John Doe / john@example.com / Pilates\n",
    "[2023-10-15 14:33:18] [Amelia API] Response (200): {\"success\":true}\n"
  ]
}
```

## Exemple d'utilisation avec PHP

```php
<?php
// Configuration
$site_url = 'https://votre-site.com';
$endpoint = '/wp-json/fpr/v1/logs';
$token = 'votre_token_ici';

// Construire l'URL complète
$url = $site_url . $endpoint . '?type=fpr&lines=20';

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

if ($http_code === 200) {
    $data = json_decode($response, true);
    foreach ($data['logs'] as $line) {
        echo $line;
    }
}
```

## Exemple d'utilisation avec JavaScript

```javascript
const fetchLogs = async () => {
  const siteUrl = 'https://votre-site.com';
  const endpoint = '/wp-json/fpr/v1/logs';
  const token = 'votre_token_ici';
  
  try {
    const response = await fetch(`${siteUrl}${endpoint}?type=fpr&lines=20`, {
      headers: {
        'X-FPR-Token': token
      }
    });
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    
    if (data.success) {
      console.log(`Type de log: ${data.log_type}`);
      console.log(`Nombre de lignes: ${data.lines}`);
      
      data.logs.forEach(line => {
        console.log(line);
      });
    }
  } catch (error) {
    console.error('Erreur lors de la récupération des logs:', error);
  }
};

fetchLogs();
```

## Sécurité

- L'accès aux logs est protégé par un token d'authentification
- Le token est généré automatiquement et peut être régénéré si nécessaire
- Les requêtes sans token valide sont rejetées avec un code HTTP 403
- Le nombre de lignes est limité pour éviter les abus

## Script de test

Un script de test est disponible dans le dossier `tests/test-remote-logs.php`. Vous pouvez l'utiliser pour vérifier que l'API fonctionne correctement.