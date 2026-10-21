# Journal des versions — Portfolio (verturin/portfolio)


### 1.10.8 — 2026-10-19
- *ACP › Albums et droits* : la modification en nombre permet aussi de **verrouiller / déverrouiller** et de **protéger / ne plus protéger** plusieurs albums d'un coup (sous-albums compris si la case est cochée). Le message final signale les albums qui restent protégés par leur album parent. Filtres, tri et membre choisi restent en place après la confirmation.

### 1.10.7 — 2026-10-18
- Bloc « Insérer dans un message du forum » : les BBCode (vignette, moyenne, grande) passent en premier ; l'adresse de la page vient en dernier, renommée « Adresse de la page (lien à cliquer, sans image) », avec un avertissement : à coller telle quelle, jamais entre [img]…[/img].

### 1.10.6 — 2026-10-18
- La permission **« Peut supprimer ses propres photos et albums »** passe à **Non par défaut** : à accorder dans *Permissions* aux groupes ou membres voulus.
- **Motif de suppression** obligatoire ou facultatif au choix de l'administrateur (*Réglages › Sécurité*), **obligatoire par défaut**, y compris pour la mise à la corbeille en lot.
- Champ de profil laissé par phpBB Gallery (« GALLERY : USERS_PERSONAL_ALBUMS ») : traduit (« Portfolio : Voir ses photos ») tant que la galerie est désactivée, et son lien mène désormais au Portfolio du membre.

### 1.10.5 — 2026-10-18
- Nouvelle permission **« Peut supprimer ses propres photos et albums »** (`u_portfolio_delete`), **accordée par défaut dans cette version (Non par défaut depuis la 1.10.6). À « Jamais », le membre ou le groupe ne peut plus supprimer ses photos ni ses albums ; il peut toujours en ajouter et les déplacer.
- *ACP › Albums et droits* : **tri** par album, propriétaire ou nombre de photos (clic sur l'en-tête de colonne, second clic pour inverser), et **recherche des albums d'un membre** avec la fenêtre « Rechercher un membre » de phpBB. Filtres et tri conservés après chaque action.

### 1.10.4 — 2026-10-17
- **Message privé quand une photo attend la validation**, réglé dans *ACP › Portfolio › Quotas et validation* : administrateurs du Portfolio (cochés par défaut), modérateurs pouvant valider, groupes et/ou membres choisis. Lien vers l'album et vers la file de validation ; un seul message par série d'envois (10 minutes) ; liste des destinataires visible dans l'ACP.

### 1.10.3 — 2026-10-17
- Nouvelle page **ACP › Portfolio › Quotas et validation** : quota de photos et d'albums **par groupe** (vide = réglage général, 0 = illimité ; la valeur la plus favorable l'emporte pour un membre de plusieurs groupes).
- Case **« Validation obligatoire »** par groupe : toutes les photos du membre attendent un modérateur, y compris dans ses propres albums, et il ne peut plus les valider lui-même.
- Nouvelle table `phpbb_portfolio_group_limits`.

### 1.10.2 — 2026-10-16
- Retrait du filigrane par les membres : bouton **« Rechercher un membre »** (fenêtre de recherche de phpBB, sélection de plusieurs membres) pour remplir la liste des membres autorisés.
- **Message privé** envoyé une seule fois à chaque membre ajouté (au nom de l'administrateur) : fonctionnement album par album et règles de sécurité. Aucun nouveau message s'il est retiré puis rajouté.
- Nouvelle page **Panneau de l'utilisateur › Portfolio › Filigrane de mes photos** : règles, albums sans filigrane, et « Réactiver le filigrane » (avec confirmation) qui remet le filigrane partout et sort le membre de la sélection ; journal d'administration et mention dans l'ACP.

### 1.10.1 — 2026-10-15
- Retrait du filigrane par le membre : la case devient « Protéger mes photos de cet album par le filigrane », **cochée par défaut** ; le membre autorisé la décoche album par album, sous-album par sous-album.
- Tout nouvel album ou sous-album démarre **avec** le filigrane, même s'il est créé dans un album qui n'en a pas : c'est toujours le membre qui décide de le retirer.

### 1.10.0 — 2026-10-15
- **Notifications du forum** pour les notes, commentaires et réactions reçus sur ses photos, avec un lien vers la photo. Nouvelle page *ACP › Portfolio › Notifications* : chaque type activable séparément, envoi **une par action** (une nouvelle note ou réaction remplace la précédente, une note, réaction ou un commentaire retiré supprime la notification) ou **regroupé** toutes les 1, 5, 15, 30 ou 60 minutes (une notification par photo), file d'attente visible et « Envoyer maintenant ». Les membres règlent ces notifications dans leurs préférences
- **Retrait du filigrane par le membre** : dans *Filigrane*, l'administration autorise des membres et/ou des groupes (avec un avertissement sur la sécurité et le vol de photos). Ces membres ont, dans chaque album et sous-album, la case « Afficher mes photos de cet album sans filigrane » (avec option pour les sous-albums). Ne vaut que pour leurs propres photos ; les autres albums gardent le filigrane sauf si leur propriétaire a fait le même choix. Liste des albums concernés et « Remettre le filigrane » dans l'ACP
- Nouvelle table `phpbb_portfolio_activity`, nouvelle colonne `album_owner_no_wm` (conservée par la sauvegarde / restauration)

### 1.9.10 — 2026-10-12
- Filigrane en **mosaïque** : nouveau réglage « Fréquence des répétitions », de 1 (très espacé) à 10 (très serré), 5 par défaut (espacement d'avant). Affiché seulement quand la disposition « Mosaïque » est choisie ; changer la fréquence recalcule les images et force les navigateurs à les recharger

### 1.9.9 — 2026-10-12
- **Filigrane désactivable par album** : *ACP › Portfolio › Filigrane › Albums sans filigrane*, choix de l'album dans une liste ; l'album et tous ses sous-albums (même créés plus tard) sont servis sans filigrane, grande taille, vignettes et images des messages comprises. Rétablissement en un clic, journal d'administration, rechargement immédiat des images
- *Albums et droits* : option « Filigrane » (appliqué / désactivé) dans la modification en nombre, étiquettes « Sans filigrane » / « Sans filigrane (album parent) » dans la liste
- Sauvegarde / restauration : le réglage est conservé

### 1.9.8 — 2026-10-11
- ACP › Albums et droits : **modification en nombre** de la visibilité et du téléchargement des originaux des albums cochés (sous-albums compris au choix), avec confirmation et journal ; filtre par visibilité ; colonne « Téléchargement »
- Bascule phpBB Gallery : les albums créés sont **réservés aux membres inscrits** et **sans téléchargement des originaux** par défaut ; deux réglages permettent de reprendre la visibilité de phpBB Gallery (ou de tout passer en privé) et d'autoriser le téléchargement. Mêmes options en ligne de commande (`--visibility`, `--download`)

### 1.9.7 — 2026-10-10
- Les photos qu'un membre publie dans un **album public** ou dans l'album d'un autre membre apparaissent aussi dans **son portfolio** (nouvelle section paginée), en bas de **son profil** et dans ses compteurs de photos (lien « Portfolio » du profil et des messages même s'il n'a aucun album à lui). Simple affichage : la photo reste dans son album, avec les droits de cet album
- Mention de l'album d'origine sous chaque photo (« dans *Concours photo* » avec l'étiquette « public »), et sur les vignettes du profil

### 1.9.6 — 2026-10-10
- Réglages › Réactions et profils : le nombre de photos affichées en bas du profil se choisit dans une liste (ne pas afficher, 3, 4, 5, 6, 8, 10, 12, 15, 20), **5 par défaut**
- Bascule phpBB Gallery : une photo dont l'album a été supprimé de la galerie n'est plus refusée. Elle est copiée dans un album « Photos récupérées de la galerie » de son auteur (un seul par membre, visible des membres inscrits). Pour les photos déjà mises de côté, cliquez sur « Réessayer ces photos » puis relancez la bascule
- Liste des photos non copiées : cases à cocher (et « tout sélectionner ») avec trois actions sur la sélection : **Réessayer**, **Copier chez le repreneur** (copie immédiate dans l'album « Photos récupérées de la galerie » du membre repreneur choisi dans *Membres partis*, qui en devient l'auteur) et **Abandonner** (la photo sort de la liste et du compteur, n'est plus retentée ; son ancien lien affiche « Photo indisponible » ; rien n'est supprimé dans phpBB Gallery)

### 1.9.5 — 2026-10-10
- **Correctif bascule phpBB Gallery (erreur 500 « Infinite recursion »)** : une arborescence abîmée dans phpBB Gallery (album parent de lui-même, ou deux albums parents l'un de l'autre) faisait tourner la recréation des albums en rond jusqu'à l'arrêt de PHP. Ces albums sont désormais recréés à la racine, et un album illisible ne bloque plus le reste de la bascule. Reproduit puis vérifié corrigé
- Membres partis : choix du membre qui reçoit les albums ligne par ligne, et option « Signer aussi les photos » pour attribuer ses photos à ce membre (elles portent alors son nom). Les membres partis qui n'ont que des photos, sans album, sont aussi listés

### 1.9.4 — 2026-10-10
- Bascule phpBB Gallery : dans la simulation et la liste des photos non copiées, le nom affiché est le **pseudo actuel** du membre. S'il a changé de pseudo depuis ses envois, l'ancien est indiqué entre parenthèses (« robert (« bob » dans la galerie) »). Un membre qui a quitté le forum est signalé « membre parti (n° …, n'existe plus sur le forum) » : phpBB Gallery conserve le nom de l'auteur avec chaque photo, même après la suppression du compte

### 1.9.3 — 2026-10-10
- Bascule phpBB Gallery : **erreur affichée photo par photo**. Chaque lot liste les photos mises de côté (raison et message d'erreur exact), le message de fin donne le nombre de photos non copiées avec un lien vers la liste détaillée, qui affiche aussi l'erreur de chacune
- Si PHP s'arrête en pleine copie (erreur fatale, mémoire épuisée), l'erreur exacte, le fichier et la photo concernée sont enregistrés et affichés en haut de la page de bascule. Une page coupée par le serveur web sans message PHP est aussi détectée (photo restée « en cours »)
- Page de bascule : version du Portfolio, `memory_limit` (et si le Portfolio peut l'augmenter), `max_execution_time`, version de GD ; nombre de photos par lot réglable (10 par défaut au lieu de 25)
- Les albums personnels déjà basculés ne sont plus réenregistrés à chaque lot (lots plus rapides sur les grosses galeries)
- Nouvelle colonne `legacy_error` dans `phpbb_portfolio_legacy`

### 1.9.2 — 2026-10-10
- **Correctif bascule phpBB Gallery** : erreur 500 au lancement de la bascule sur une grosse galerie (la simulation fonctionnait). Un lot de 25 grandes photos dépassait le délai du serveur web. Les lots sont désormais limités à ~12 secondes et s'enchaînent tout seuls
- Chaque photo est notée « en cours » avant d'être traitée : si le serveur s'arrête dessus (mémoire, délai), le lot suivant passe à la suite au lieu de replanter sur la même photo
- Photo trop grande pour la mémoire PHP que l'hébergeur autorise : refusée proprement (bascule et envois) au lieu d'une erreur 500. Raisons « mémoire » et « arrêt du serveur » dans la liste des fichiers introuvables
- La bascule ne refuse plus les photos de la galerie plus lourdes que le poids maximal des envois du Portfolio
- Nouvelle colonne `legacy_note` (raison de l'échec) dans `phpbb_portfolio_legacy`

### 1.9.1 — 2026-10-10
- **Correctif** : « Déplacer vers un autre album » proposait les albums des autres membres (notamment à un administrateur, qui peut envoyer partout). Une photo ne se déplace plus que dans les albums et sous-albums du même portfolio, ou vers les albums de la personne qui l'a postée ; règle vérifiée aussi côté serveur (fiche photo, modification, déplacement groupé). La restauration d'une photo dont l'album a disparu ne propose que les albums de son auteur
- Bascule phpBB Gallery : le compteur « Fichiers introuvables » mène à la liste détaillée (photo, membre, album, chemin attendu, raison) avec un bouton « Réessayer ces photos »

### 1.9.0 — 2026-10-10
- **Membres partis** : quand un membre est supprimé (ACP, élagage des inactifs, extension de nettoyage), ses albums sont rattachés à un **membre repreneur choisi par son nom dans l'ACP** (à défaut, le premier fondateur), rangés dans un album « Photos de … ». Ses photos et commentaires restent signés de son nom, sans lien. Les albums « Mes amis » deviennent privés, les partages sont conservés, les anciens liens vers son portfolio mènent à l'album de rangement
- Réglage du sort de ses photos : toujours conservées, suivre le choix « Conserver / Supprimer les messages », ou toujours à la corbeille
- Nouvelle page **ACP › Membres partis** : choix du repreneur, liste des photos orphelines existantes (membres supprimés avant cette version, ancienne galerie) avec le nom retrouvé dans phpBB Gallery, rattachement membre par membre ou en une fois, historique
- Nouvelle table `phpbb_portfolio_departed`

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
