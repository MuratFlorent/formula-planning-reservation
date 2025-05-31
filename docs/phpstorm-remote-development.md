# Examen du code à distance avec PhpStorm

Ce document explique comment configurer PhpStorm pour examiner et modifier le code directement sur un serveur distant.

## Méthodes disponibles

PhpStorm offre plusieurs méthodes pour travailler avec du code sur un serveur distant :

### 1. Déploiement via SFTP/FTP

Cette méthode permet de synchroniser les fichiers entre votre machine locale et le serveur distant.

#### Configuration :

1. Ouvrez PhpStorm et allez dans **File > Settings > Build, Execution, Deployment > Deployment**
2. Cliquez sur le bouton **+** pour ajouter une nouvelle configuration
3. Donnez un nom à votre serveur (ex: "Serveur Production")
4. Choisissez le type de connexion (SFTP, FTP, FTPS)
5. Configurez les paramètres de connexion :
   - Host: l'adresse du serveur
   - Port: généralement 22 pour SFTP, 21 pour FTP
   - Root path: le chemin vers le répertoire racine du projet sur le serveur
   - User name et Password: vos identifiants
6. Testez la connexion avec le bouton **Test Connection**
7. Dans l'onglet **Mappings**, configurez le mappage entre les dossiers locaux et distants
8. Cliquez sur **OK** pour sauvegarder

Une fois configuré, vous pouvez :
- Télécharger des fichiers depuis le serveur (**Tools > Deployment > Download from...**)
- Uploader des fichiers vers le serveur (**Tools > Deployment > Upload to...**)
- Activer la synchronisation automatique (**Tools > Deployment > Automatic Upload**)

### 2. Remote Interpreter

Pour le développement PHP, vous pouvez configurer un interpréteur PHP distant :

1. Allez dans **File > Settings > PHP**
2. Cliquez sur **...** à côté de "CLI Interpreter"
3. Cliquez sur **+** et sélectionnez **From Docker, Vagrant, VM, WSL, Remote...**
4. Choisissez **SSH Credentials**
5. Configurez la connexion SSH
6. Spécifiez le chemin vers l'exécutable PHP sur le serveur distant
7. Cliquez sur **OK**

### 3. Remote Development via SSH Gateway

PhpStorm 2022.3+ offre une fonctionnalité de développement à distance via SSH Gateway :

1. Allez dans **File > Remote Development**
2. Cliquez sur **SSH Connection**
3. Configurez la connexion SSH
4. Choisissez le dossier du projet sur le serveur distant
5. PhpStorm se connectera au serveur et ouvrira le projet distant

### 4. Utilisation de Xdebug pour le débogage à distance

Pour déboguer le code sur un serveur distant :

1. Configurez Xdebug sur le serveur distant
2. Dans PhpStorm, allez dans **File > Settings > PHP > Debug**
3. Configurez les ports de débogage (par défaut 9000)
4. Activez "Can accept external connections"
5. Configurez un serveur de débogage dans **File > Settings > PHP > Servers**
6. Activez le bouton d'écoute de débogage dans PhpStorm

## Recommandations pour ce projet

Pour le plugin Formula Planning Reservation, nous recommandons :

1. **Pour le développement quotidien** : Utiliser le déploiement SFTP avec synchronisation automatique
2. **Pour le débogage** : Configurer Xdebug pour le débogage à distance
3. **Pour l'édition intensive** : Utiliser Remote Development via SSH Gateway

## Avantages et inconvénients

### Avantages
- Travail direct sur l'environnement de production ou de staging
- Pas besoin de configurer un environnement de développement local complet
- Débogage en conditions réelles

### Inconvénients
- Dépendance à la connexion internet
- Risque de modifications directes en production
- Performance potentiellement réduite selon la qualité de la connexion

## Bonnes pratiques

1. Toujours travailler sur un environnement de staging plutôt que directement en production
2. Utiliser un système de contrôle de version (Git)
3. Faire des sauvegardes régulières
4. Configurer correctement les mappages de fichiers pour éviter les erreurs

## Ressources supplémentaires

- [Documentation officielle PhpStorm sur le déploiement](https://www.jetbrains.com/help/phpstorm/deploying-applications.html)
- [Documentation sur le débogage à distance](https://www.jetbrains.com/help/phpstorm/debugging-a-php-cli-script.html)
- [Guide du développement à distance](https://www.jetbrains.com/help/phpstorm/remote-development-overview.html)