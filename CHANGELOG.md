# Journal des versions — Portfolio (verturin/portfolio)

### 1.8.6 — 2026-10-10
- Correctif important : le service de réglages du Portfolio héritait de `\phpbb\config\config`. Les extensions qui utilisent l'« autowiring » (par ex. dmzx/scheduledmaintenance) ne savaient plus quel service choisir : « Cannot autowire service … references class phpbb\config\config », et phpBB désactivait temporairement toutes les extensions. Les réglages sont désormais une classe indépendante ; reproduit puis vérifié corrigé avec une extension en autowiring
- Coexistence avec phpBB Gallery vérifiée (elle n'était pas en cause)

### 1.8.5 — 2026-10-09
- Nom du membre qui a posté la photo sous chaque vignette (toutes les grilles) et dans la visionneuse

### 1.8.4 — 2026-10-09
- Correctif : avertissements « Undefined array key TOO_FEW_CHARS » à la création d'une page (ACP) avec un texte d'introduction vide ; les descriptions vides ou très courtes ne déclenchent plus d'avertissement nulle part
- Pages dans le forum : nouveau contenu « Albums de tous les membres »
- Tests désormais exécutés avec l'affichage des avertissements PHP de phpBB activé

### 1.8.3 — 2026-10-09
- Partage : rôle modifiable (invité, contributeur, co-gestionnaire) et retrait, avec confirmation, sur la page Partage et sur la page de chaque album
- Album : le bouton « ⋯ » est aligné sur les autres boutons et son menu n'est plus coupé par le cadre ; il se ferme en cliquant ailleurs

### 1.8.2 — 2026-10-09
- Aperçu rapide et fiche photo : lien, BBCode et accès à la fiche réservés au posteur et aux co-gestionnaires ; les autres membres notent, réagissent et commentent seulement
- Fiche photo : la colonne de droite ne déborde plus (rotation sur une ligne de trois boutons, liste de déplacement raccourcie)
- « Définir comme couverture » devient « Utiliser comme image de l'album », avec une explication au survol

### 1.8.1 — 2026-10-09
- Étiquette verte « Partagé · n » sur les albums du propriétaire (n = nombre de membres ayant accès, partages hérités compris)

### 1.8.0 — 2026-10-09
- Pages Portfolio affichées dans le forum : plus récentes, mieux notées, derniers commentaires, au hasard ou un album précis ; publiées par un forum-lien dont les permissions de groupe décident qui voit la page
- Albums protégés : dépôt possible, suppression et déplacement réservés à l'équipe du forum

### 1.7.0 — 2026-10-09
- Filigrane : contour autour du texte (avant, un texte blanc était invisible sur les photos claires) ; bouton « Recalculer tous les filigranes » qui force aussi les navigateurs à recharger les images
- Partage album par album, avec héritage par les sous-albums ; conversion automatique de l'ancien partage global ; formulaire de partage réaligné (et utilisable sur mobile)
- Étiquettes de visibilité masquées pour les membres, affichables pour les administrateurs (réglage ACP) ; étiquettes regroupées sur une ligne sans chevauchement
- Taille d'affichage des vignettes réglable (ACP) et régénération des vignettes par lots
- Lien et BBCode d'insertion toujours dépliés sur la fiche photo, et présents aussi dans l'aperçu rapide

### 1.6.0 — 2026-10-09
- Filigrane : les vignettes sont désormais filigranées (texte agrandi pour rester lisible). Le propriétaire voit ses photos sans filigrane si l'option « pas de filigrane pour le propriétaire » est active ; un message le lui rappelle sur la fiche
- Icône Portfolio de la barre de navigation remplacée par l'icône Font Awesome du forum (même style que les autres liens)
- Profil : lien vers le portfolio et dernières photos du membre en bas du profil
- Création d'albums : visibilités, visibilité par défaut (Membres inscrits), verrouillage, téléchargement et tri gérés dans l'ACP. « Groupes choisis » réservé aux administrateurs et à la nouvelle permission `u_portfolio_vis_groups`
- Fiche photo : rotation, déplacement, couverture, modification, suppression et mention EXIF réservées au posteur et aux membres qu'il a autorisés ; encadré « Modération » séparé pour les modérateurs
- Badges de partage lisibles et différents selon le type ; section « Mes albums » en tête de l'accueil
- Aperçu rapide : noter, réagir et commenter depuis les grilles sans ouvrir la fiche
- Lien et BBCode `[portfolio=mini|medium|large]` pour insérer proprement les photos dans les messages
- Mise à jour : remplacez le dossier puis lancez la mise à jour de la base (désactiver / réactiver l'extension, ou `php bin/phpbbcli.php db:migrate`)

### 1.5.1 — 2026-10-09
- Correction : envoi de photos impossible sur MySQL / MariaDB en mode strict (« Field 'photo_desc' doesn't have a default value [1364] »). Les champs texte sont maintenant toujours renseignés à la création d'une photo, d'un album et lors d'un import
- Correction : les réactions sont incluses dans les sauvegardes complètes, les exports et les restaurations
- Testé sur MariaDB 10.11 en mode strict (préfixe de tables `cetc_`) en plus de SQLite
- Migration `v151_mysql_strict` (version uniquement)

### 1.5.0 — 2026-10-09
- Nouveau : bascule phpBB Gallery → Portfolio (un membre, un groupe ou tout le forum), avec simulation, traitement par lots, reprise et annulation. Sont repris les albums et sous-albums, les dates, les vues, les notes (converties de 1-10 en 1-5) et les commentaires
- Nouveau : redirection permanente des anciens liens `/gallery/…` dès qu'une photo est basculée. BBCode `[image]` repris par le Portfolio sans modifier les messages, et recréé automatiquement après la suppression de phpBB Gallery
- Nouveau : réécriture facultative et annulable des liens dans les messages et signatures
- Nouveau : commande `portfolio:migrate-gallery`
- Nouveau : rotation en un clic sur la page photo (↺ ↻ 180°), enregistrée immédiatement
- Nouveau : bouton « Déplacer » vers un autre album ou sous-album sur la page photo
- Nouveau : réactions par émoji sur les photos, liste réglable dans l'ACP, permission `u_portfolio_react`
- Nouveau : téléchargement des originaux autorisé ou non par le propriétaire, album par album
- Nouveau : lien vers la galerie dans le profil et le mini-profil des messages, activable dans l'ACP et réservé par groupe (`u_portfolio_profile_link`)
- Correction : quand un envoi échoue, le message indique la vraie cause (erreur PHP, limite `post_max_size`, page d'erreur de l'hébergeur) au lieu de « Erreur réseau »
- Migration `v150_gallery_reactions` : tables `portfolio_reactions`, `portfolio_legacy`, `portfolio_legacy_texts`, colonne `album_download`

### 1.4.1 — 2026-10-09
- Administration habillée avec l'ACP Skin de Chastity Tracker (même présentation que cette extension) sur les 5 pages ACP
- Ancien `acp_portfolio_common.html` remplacé par `adm/style/portfolio_acp.css`
- Encadrés, pastilles, rangées de boutons et tableaux convertis aux classes `skin-*` ; styles en ligne supprimés
- Migration `v141_acp_skin` (version uniquement)

### 1.4.0 — 2026-10-09
- Nouveau : export ZIP d'un album (avec sous-albums) ou de tout son portfolio, avec un lien de téléchargement unique, personnel et à durée limitée
- Nouveau : sauvegarde complète de toutes les données de l'extension depuis l'ACP
- Nouveau : import (ajout) et restauration complète (remplacer), membres et groupes retrouvés par leur nom
- Nouveau : commandes `portfolio:backup` et `portfolio:restore` pour les grosses galeries
- Nouvelle permission `u_portfolio_export` ; réglages de durée des liens et de limite quotidienne
- Tâche cron de nettoyage des exports expirés
- Migration `v140_archive` : table `phpbb_portfolio_exports`

### 1.3.0 — 2026-10-09
- Message « Vous n'êtes pas autorisé à afficher cet album / cette photo » avec la raison (amis, privé, groupes, connexion) au lieu de « introuvable »
- Boutons « Demander à X de m'ajouter en ami » et « Demander l'accès à X » (message privé au propriétaire)
- Nouvelle visibilité « Mes amis » (liste d'amis phpBB du propriétaire)
- Option « autoriser aussi mes amis » en plus des groupes choisis
- Nouveau rôle « Invité » (lecture seule) pour ouvrir un album privé à des membres précis
- Choix de visibilité présentés sous forme de cartes expliquées
- Migration `v130_privacy` : colonne `album_friends`, rôles renumérotés (invité 1, contributeur 2, co-gestionnaire 3), avec conversion automatique des partages existants

### 1.2.0 — 2026-10-09
- Nouveau : barre de navigation sous chaque photo (première, précédente, suivante, dernière, position, retour à l'album sur la bonne page)
- Nouveau : pellicule de vignettes autour de la photo courante
- Nouveau : liens vers l'album précédent ou suivant en fin ou début d'album
- Nouveau : navigation entre albums du même niveau, bouton « remonter d'un niveau », arborescence dépliable de l'album racine
- Le tri de l'album est conservé d'une photo à l'autre ; compteur dans la visionneuse plein écran
- Migration `v120_navigation` (aucun changement de schéma)

### 1.1.0 — 2026-10-09
- Nouveau : filigrane dynamique traçable (nom du site + ID du visiteur), ajouté à l'affichage et au téléchargement
- Dispositions : 4 coins, centre, en travers, en diagonale, mosaïque. Taille du texte, couleur, opacité et ombre réglables
- Logo du site en filigrane (envoi depuis l'ACP, position, taille et opacité)
- Exception possible pour le propriétaire et les co-gestionnaires, filigrane des vignettes en option
- Cache des images filigranées avec purge automatique (tâche cron) et bouton « Vider le cache »
- Nouvelle page ACP « Filigrane » avec aperçu en direct
- Réglages du filigrane rangés dans la table propre `phpbb_portfolio_config` (migration `v110_watermark`)

### 1.0.0 — 2026-10-09
- Première version : réécriture complète inspirée de phpBB Gallery
- Albums, sous-albums, albums publics et personnels, couverture, tri, verrouillage
- Albums gérés à plusieurs : co-gestionnaires et contributeurs, partage par album ou sur tout le portfolio
- Envoi multiple par glisser-déposer, suppression EXIF/GPS avec redressement automatique, contrôle du type réel
- Notation 1-5 sans rechargement, commentaires, notifications (commentaire, validation, alerte de suppression massive)
- Corbeille : restauration par le membre, validation définitive par l'administrateur, alerte et blocage optionnel des suppressions massives
- Suppression d'un album uniquement une fois vide
- Droits par groupe sur toutes les fonctions et par album
- Tables et réglages propres à l'extension (`phpbb_portfolio_*`)
- ACP : réglages, albums et droits par groupe, corbeille
- Langues : français et anglais

