# Portfolio — extension phpBB

[![Vérifications](https://github.com/verturin/portfolio/actions/workflows/ci.yml/badge.svg)](https://github.com/verturin/portfolio/actions/workflows/ci.yml)
[![Dernière version](https://img.shields.io/github/v/release/verturin/portfolio?label=version)](https://github.com/verturin/portfolio/releases/latest)
![phpBB](https://img.shields.io/badge/phpBB-3.3.15%2B%20%C2%B7%204.x-blue)
![PHP](https://img.shields.io/badge/PHP-7.2%20%E2%80%93%208.4-777bb4)
[![Licence GPL-2.0](https://img.shields.io/badge/licence-GPL--2.0-green)](license.txt)

🇬🇧 [English version](README.md) · 📜 [Journal des versions](CHANGELOG.md)

Galerie photos moderne pour phpBB : albums et sous-albums, albums gérés à plusieurs, notation, commentaires, corbeille avec validation par l'administrateur, suppression des données EXIF et droits par groupe sur toutes les fonctions.

- **Nom technique** : `verturin/portfolio`
- **Version** : 1.10.8
- **Bases de données testées** : MySQL / MariaDB (mode strict compris) et SQLite
- **Compatibilité** : phpBB 3.3.15 minimum, préparée pour phpBB 4 · PHP 7.2 à 8.4 · extension PHP GD obligatoire (EXIF conseillée)
- **Licence** : GPL-2.0-only
- **Auteur** : [verturin](https://github.com/verturin)

## Captures d'écran

| | |
|---|---|
| **Accueil du Portfolio**<br>[![Accueil du Portfolio](docs/screenshots/fr/01-home.png)](docs/screenshots/fr/01-home.png) | **Portfolio d’un membre**<br>[![Portfolio d’un membre](docs/screenshots/fr/02-member-portfolio.png)](docs/screenshots/fr/02-member-portfolio.png) |
| **Album et sous-albums**<br>[![Album et sous-albums](docs/screenshots/fr/03-album.png)](docs/screenshots/fr/03-album.png) | **Page photo : note, réactions, insertion**<br>[![Page photo : note, réactions, insertion](docs/screenshots/fr/04-photo.png)](docs/screenshots/fr/04-photo.png) |
| **Aperçu rapide**<br>[![Aperçu rapide](docs/screenshots/fr/05-quick-view.png)](docs/screenshots/fr/05-quick-view.png) | **Envoi par glisser-déposer**<br>[![Envoi par glisser-déposer](docs/screenshots/fr/06-upload.png)](docs/screenshots/fr/06-upload.png) |
| **Réglages d’un album**<br>[![Réglages d’un album](docs/screenshots/fr/07-album-settings.png)](docs/screenshots/fr/07-album-settings.png) | **Partage et co-gestion**<br>[![Partage et co-gestion](docs/screenshots/fr/08-sharing.png)](docs/screenshots/fr/08-sharing.png) |
| **Codes d’insertion**<br>[![Codes d’insertion](docs/screenshots/fr/09a-embed-codes.png)](docs/screenshots/fr/09a-embed-codes.png) | **Photo dans un message**<br>[![Photo dans un message](docs/screenshots/fr/09b-photo-in-post.png)](docs/screenshots/fr/09b-photo-in-post.png) |
| **Notifications**<br>[![Notifications](docs/screenshots/fr/10-notifications.png)](docs/screenshots/fr/10-notifications.png) | **Corbeille**<br>[![Corbeille](docs/screenshots/fr/11-recycle-bin.png)](docs/screenshots/fr/11-recycle-bin.png) |
| **Profil du membre**<br>[![Profil du membre](docs/screenshots/fr/12-profile.png)](docs/screenshots/fr/12-profile.png) | **Filigrane traçable**<br>[![Filigrane traçable](docs/screenshots/fr/13-watermark.png)](docs/screenshots/fr/13-watermark.png) |
| **ACP : réglages**<br>[![ACP : réglages](docs/screenshots/fr/14-acp-settings.png)](docs/screenshots/fr/14-acp-settings.png) | **ACP : albums et droits**<br>[![ACP : albums et droits](docs/screenshots/fr/15-acp-albums.png)](docs/screenshots/fr/15-acp-albums.png) |
| **ACP : filigrane**<br>[![ACP : filigrane](docs/screenshots/fr/16-acp-watermark.png)](docs/screenshots/fr/16-acp-watermark.png) | **ACP : quotas et validation**<br>[![ACP : quotas et validation](docs/screenshots/fr/17-acp-quotas-approval.png)](docs/screenshots/fr/17-acp-quotas-approval.png) |
| **ACP : notifications**<br>[![ACP : notifications](docs/screenshots/fr/18-acp-notifications.png)](docs/screenshots/fr/18-acp-notifications.png) | **ACP : bascule phpBB Gallery**<br>[![ACP : bascule phpBB Gallery](docs/screenshots/fr/19-acp-phpbb-gallery-import.png)](docs/screenshots/fr/19-acp-phpbb-gallery-import.png) |
| **ACP : sauvegarde**<br>[![ACP : sauvegarde](docs/screenshots/fr/20-acp-backup.png)](docs/screenshots/fr/20-acp-backup.png) | **Permissions**<br>[![Permissions](docs/screenshots/fr/21-acp-permissions.png)](docs/screenshots/fr/21-acp-permissions.png) |

## Fonctions

### Albums
- **Choix laissés aux membres (v1.6.0)** : dans *ACP › Réglages › Création d'albums par les membres*, l'administrateur choisit les visibilités proposées (Tout le monde, Membres inscrits, Mes amis, Privé ; « Groupes choisis » est réservé aux administrateurs et aux groupes ayant la permission `u_portfolio_vis_groups`), la visibilité par défaut (**Membres inscrits**), et si les membres peuvent verrouiller leurs albums, choisir le téléchargement et l'ordre des photos
- **Badges (v1.6.0, revus en 1.7.0)** : une étiquette orange signale un album partagé avec vous (Partagé avec moi, Contributeur, Co-gestionnaire). Les étiquettes de visibilité (Public, Membres, Groupes, Amis, Privé) ne sont jamais montrées aux membres ; les administrateurs peuvent les afficher pour eux (*ACP › Réglages › Affichage*)
- **Taille des vignettes (v1.7.0)** réglable dans l'ACP (100 à 400 px), avec un bouton « Régénérer les vignettes » après un changement de taille
- **Étiquette « Partagé · n » (v1.8.1)** sur vos propres albums : vous voyez d'un coup d'œil qu'un album est partagé et avec combien de membres (partage hérité de l'album parent compris). Le survol donne le détail
- **Mes albums (v1.6.0)** en tête de l'accueil, suivis des albums partagés avec moi
- Albums personnels pour chaque membre et albums publics créés par l'administration
- Sous-albums sur plusieurs niveaux (profondeur maximale réglable), déplacement d'un album sous un autre, couverture au choix
- Visibilité : tout le monde, membres inscrits, groupes choisis ou privé. Un sous-album n'est jamais plus ouvert que son parent
- Verrouillage (plus d'envoi, de note ni de commentaire) et tri par défaut (date, titre, note, vues)
- **Un album ne peut être supprimé qu'une fois vide** (ni photo, ni sous-album). La sélection multiple permet de le vider en un clic

### Navigation (v1.2.0)
- **Sur une photo** : boutons *Première*, *Précédente*, *Suivante* et *Dernière*, position (« Photo 3 sur 12 »), bouton *Retour à l'album* qui renvoie sur la bonne page de l'album, et **pellicule** de vignettes autour de la photo courante
- En fin d'album, un lien mène directement à l'**album suivant** (et en début d'album, à l'album précédent)
- **Dans un album** : bouton pour **remonter d'un niveau** (album parent ou portfolio du membre), boutons **album précédent / suivant** entre les albums du même niveau, position (« Album 2 sur 5 »)
- **Arborescence** dépliable de tout l'album racine (sous-albums de tous niveaux, nombre de photos, album courant mis en évidence)
- Le tri choisi dans l'album (date, titre, note, vues) est **conservé** en passant d'une photo à l'autre
- Clavier : ← / → pour la photo ou l'album précédent / suivant, Échap pour fermer la visionneuse. La visionneuse plein écran affiche aussi « 3 / 12 »
- Seuls les albums et photos que le visiteur a le droit de voir apparaissent dans la navigation

### Qui peut voir un album ? (v1.3.0)
| Visibilité | Qui voit l'album |
|---|---|
| Tout le monde | Tous, invités compris |
| Membres inscrits | Les membres connectés |
| Groupes choisis | Les groupes cochés, **et en option les amis du propriétaire** |
| Mes amis | Les membres de la **liste d'amis phpBB** du propriétaire (*Panneau de l'utilisateur › Amis et ignorés*) |
| Privé | Le propriétaire et les personnes **invitées** (rôle « Invité ») |

Dans tous les cas, le propriétaire, les co-gestionnaires, les contributeurs, les invités, les modérateurs et les administrateurs voient l'album. Un sous-album n'est jamais plus ouvert que son parent.

**Message clair en cas de refus** : au lieu de « introuvable », le visiteur voit « Vous n'êtes pas autorisé à afficher cet album / cette photo » avec la raison :
- *album réservé aux amis de X* : bouton « Demander à X de m'ajouter en ami » (message privé) ;
- *album privé* : bouton « Demander l'accès à X » ;
- *album réservé à certains groupes* : message simple ;
- *visiteur non connecté* : formulaire de connexion.

« Introuvable » n'apparaît plus que pour un album ou une photo qui n'existe pas, ou plus. Les images elles-mêmes renvoient une erreur 403 (accès refusé).

### Albums gérés à plusieurs (mari et femme, famille…)
- **Modifier ou retirer un partage (v1.8.3)** : sur la page Partage comme sur la page « Co-gestionnaires et invités » d'un album, chaque membre a une liste pour changer son rôle (le bouton « Modifier » apparaît dès qu'on change la valeur) et un bouton « Retirer ». Les deux demandent une confirmation
- **Partage album par album (v1.7.0)** : page *Partage* → on choisit l'album, le membre et son rôle (invité, contributeur, co-gestionnaire). **Les sous-albums héritent du partage de leur album parent.** Un membre invité sur un sous-album le voit même si l'album parent reste privé. L'ancien partage « tous mes albums » est converti automatiquement à la mise à jour (un partage sur chaque album principal)
- **Invité** (v1.3.0) : voit l'album, même privé, sans rien pouvoir modifier. Pour ouvrir un album à quelques personnes précises
- **Co-gestionnaire** : gère l'album comme le propriétaire (envoi, édition, déplacement, suppression, sous-albums)
- **Contributeur** : envoie des photos et ne gère que les siennes
- Le partage se fait sur un album (sous-albums compris) **ou sur tout le portfolio** (page « Partage »), y compris pour les albums créés plus tard
- Seul le propriétaire peut supprimer l'album ou modifier le partage

### Photos
- Envoi multiple par glisser-déposer, avec barre de progression et titre modifiable avant l'envoi
- **Suppression des données EXIF, GPS, IPTC et XMP** : la photo est d'abord redressée selon son orientation, puis ré-encodée sans aucune métadonnée
- Contrôle du vrai type de fichier (un fichier PHP renommé en `.jpg` est refusé), originaux trop grands réduits automatiquement
- Vignettes carrées, taille d'affichage et original. Les fichiers ne sont jamais servis directement : chaque image passe par un contrôleur qui vérifie les droits
- Rotation, déplacement, téléchargement de l'original (selon permission), visionneuse plein écran (clavier et balayage tactile)
- Validation des photos par les modérateurs pour les groupes qui n'ont pas la permission « sans validation »

### Quotas et validation par groupe (v1.10.3)
- Page *ACP › Portfolio › Quotas et validation* : pour chaque groupe, **nombre maximum de photos**, **nombre maximum d'albums** et case **« Validation obligatoire »**. Case vide = réglage général de la page Réglages, 0 = illimité
- Un membre de plusieurs groupes obtient la valeur la plus favorable (illimité l'emporte, sinon le plus grand nombre)
- **Validation obligatoire** : chaque photo envoyée par un membre du groupe attend l'accord d'un modérateur, **même dans ses propres albums**, et il ne peut pas la valider lui-même. Sans cette case, la permission « Peut publier des photos sans validation » décide, et les photos publiées dans ses propres albums ne sont jamais retenues
- Ne sont concernés ni par les quotas ni par la validation obligatoire : la permission « N'est pas soumis aux quotas » (pour les quotas), les modérateurs du Portfolio et les administrateurs
- **Message privé « photo à valider » (v1.10.4)** : sur la même page, choix des destinataires prévenus quand une photo attend la validation : administrateurs du Portfolio, modérateurs ayant la permission de valider, groupes choisis et/ou membres choisis (avec « Rechercher un membre »). Le message indique le membre, l'album, et donne le lien vers la file de validation. Un envoi de plusieurs photos ne produit qu'un seul message (pas de nouveau message pendant 10 minutes tant que les photos précédentes attendent). Il est envoyé au nom de l'administrateur qui a enregistré le réglage ; le membre qui publie ne le reçoit jamais. La page affiche la liste des destinataires actuels

### Filigrane traçable (v1.1.0)
- **v1.7.0** : contour de couleur opposée autour du texte, lisible sur un ciel blanc comme sur une photo sombre ; bouton **« Recalculer tous les filigranes »** (cache vidé et adresses des images changées, pour que les navigateurs rechargent aussi leurs copies)
- **Le filigrane est ajouté au moment de l'affichage**, pour chaque visiteur : nom du site et **ID du membre qui regarde ou télécharge** la photo. Une copie volée indique quel compte l'a récupérée
- Texte libre avec balises `{SITENAME}`, `{SITEURL}`, `{USER_ID}`, `{USERNAME}`, `{PHOTO_ID}`, `{DATE}` (ex. `© {SITENAME} · ID {USER_ID}`)
- Disposition au choix : un des 4 coins, centre, **en travers** (bandeau), **en diagonale** ou **en mosaïque** répétée sur toute la photo
- Réglage de la taille du texte (en % de la largeur), de la couleur, de l'**opacité**, de l'ombre portée, et activation ou non
- **Logo du site** (PNG transparent) : position, taille et opacité réglables
- Par défaut, pas de filigrane pour le propriétaire et ses co-gestionnaires : il apparaît quand on consulte l'album d'un autre membre. Les vignettes ne sont pas filigranées (réglable)
- **Fréquence des répétitions en mosaïque (v1.9.10)** : avec la disposition « Mosaïque (répété sur toute la photo) », un curseur règle l'écart entre les répétitions, de 1 (texte très espacé) à 10 (très serré) ; 5 correspond au réglage d'avant. Le curseur n'apparaît que lorsque « Mosaïque » est choisie ; les images sont recalculées et les navigateurs les rechargent aussitôt
- **Retrait du filigrane par le membre (v1.10.0)** : *ACP › Portfolio › Filigrane › Retrait du filigrane par les membres*, l'administration désigne des membres (par leur nom) et/ou des groupes. Ces membres voient dans le formulaire de chacun de leurs albums et sous-albums la case « Protéger mes photos de cet album par le filigrane », **cochée par défaut** : ils la décochent **album par album** (chaque sous-album a sa propre case) là où ils veulent montrer leurs photos sans filigrane, avec un avertissement sur la copie des photos (et un raccourci « Appliquer aussi à tous mes sous-albums »). Tout nouvel album ou sous-album démarre avec le filigrane, même créé dans un album qui n'en a pas (v1.10.1). Le réglage ne vaut **que pour leurs propres photos** : les photos d'autres membres publiées dans leur album gardent le filigrane, et quand ils regardent l'album d'un autre membre le filigrane reste, sauf si cet autre membre a lui aussi retiré le filigrane de cet album. La page ACP rappelle les risques (vol de photos, aucune trace), liste les albums concernés avec leur état et permet de « Remettre le filigrane » ; retirer l'autorisation d'un membre remet aussitôt le filigrane sur ses photos
- **Message privé et renoncement (v1.10.2)** : la liste des membres autorisés se remplit avec « **Rechercher un membre** » (la fenêtre de recherche de phpBB, plusieurs membres à la fois) ou à la main. Chaque membre ajouté reçoit **une seule fois** un **message privé** envoyé au nom de l'administrateur : fonctionnement (case par album, nouvel album protégé) et règles de sécurité ; le retirer puis le rajouter ne renvoie pas de message. Les membres autorisés par un groupe ne reçoivent pas ce message. Dans le **panneau de l'utilisateur › Portfolio › Filigrane de mes photos**, le membre retrouve les règles, la liste de ses albums sans filigrane, et une case « Réactiver le filigrane » (avec confirmation) qui remet le filigrane sur toutes ses photos et **le sort de la sélection** (même s'il était autorisé par un groupe) ; l'action est inscrite au journal d'administration et la page Filigrane de l'ACP indique qui a renoncé. Le rajouter dans la liste lui rend l'autorisation, sans nouveau message
- **Albums sans filigrane (v1.9.9)** : *ACP › Portfolio › Filigrane › Albums sans filigrane* : choisir un album et « Désactiver le filigrane ». Toutes les photos de cet album **et de tous ses sous-albums** (y compris ceux créés plus tard) s'affichent alors sans filigrane, en grande taille comme en vignette et dans les messages. La liste des albums concernés permet de « Rétablir le filigrane ». Le même réglage existe dans la modification en nombre de *Albums et droits* (colonne d'étiquettes « Sans filigrane » / « Sans filigrane (album parent) ») ; les navigateurs rechargent les images aussitôt
- Les originaux enregistrés ne sont jamais modifiés. Les images filigranées sont gardées en cache par visiteur puis purgées automatiquement (durée réglable, bouton « Vider le cache »)
- Aperçu en direct dans l'ACP (*ACP › Extensions › Portfolio › Filigrane*)
- Police DejaVu Sans Bold fournie (licence libre, voir `fonts/DejaVu-LICENSE.txt`)

### Retouches rapides et réactions (v1.5.0)
- **Rotation en un clic** sur la page photo (↺ 90°, ↻ 90°, 180°) : l'image est corrigée et enregistrée tout de suite, sans recharger la page. Original, vignette et taille d'affichage sont mis à jour
- **Déplacer une photo** vers un autre album ou sous-album directement depuis sa page. C'est aussi possible en lot (sélection multiple dans l'album) et depuis « Modifier la photo »
- **Réactions** en plus de la note (👍 ❤️ 😀 😲 😢 👏 …) : un clic pour réagir, le même pour retirer, une autre pour changer. Le survol montre qui a réagi. La liste des émojis se choisit dans l'ACP (12 disponibles) et la permission `u_portfolio_react` règle qui peut réagir, par groupe
- **Téléchargement autorisé ou non par album** (case dans le formulaire de l'album) : si le propriétaire refuse, le bouton disparaît et l'original est bloqué (403). La photo reste visible, avec le filigrane s'il est activé. Propriétaire, co-gestionnaires et administrateurs gardent l'accès

### Qui a posté la photo ? (v1.8.5)
- Sous **chaque vignette**, « par *pseudo* » indique le membre qui a posté la photo, avec un lien vers son portfolio. Indispensable dans un album public ou un album géré à plusieurs. Le nom apparaît aussi dans la visionneuse (« Titre — postée par … »)

### Notation et commentaires
- Note de 1 à 5 étoiles, modifiable ou retirable, enregistrée sans rechargement de la page
- Commentaires avec BBCode ; le propriétaire de la photo est prévenu des notes, commentaires et réactions dans les notifications du forum (v1.10.0)
- **Aperçu rapide (v1.6.0)** : un clic sur une vignette ouvre la photo avec, à côté, les étoiles, les réactions et les derniers commentaires. On note, on réagit et on commente sans quitter la grille. Sur les vignettes, un petit résumé reste visible (★ note, réactions, nombre de commentaires). Désactivable dans l'ACP

### Qui peut modifier une photo ? (v1.6.0)
- Rotation, déplacement, couverture d'album, modification et suppression sont réservés **à la personne qui a posté la photo et aux membres qu'elle a autorisés** (propriétaire de l'album, co-gestionnaires). Ces boutons sont regroupés dans « Gérer cette photo », avec la mention « Données EXIF / GPS supprimées »
- **Déplacer une photo (v1.9.1)** : uniquement vers les albums et sous-albums du même portfolio (le propriétaire de l'album où elle se trouve), ou vers les albums de la personne qui l'a postée. Jamais vers l'album d'un autre membre, même pour un administrateur. Même règle pour le déplacement groupé et pour la restauration d'une photo dont l'album a disparu (vers les albums de son auteur)
- Les modérateurs (`m_portfolio_edit`, `m_portfolio_delete`) ne voient qu'un encadré « Modération » replié, pour modifier ou mettre à la corbeille une photo contraire aux règles. Un administrateur ne peut plus faire pivoter, déplacer ni changer la couverture des photos d'un membre

### Insérer une photo dans un message (v1.6.0)
- **v1.8.2** : le lien et les BBCode ne sont proposés qu'à la personne qui a posté la photo et aux membres qu'elle a autorisés (co-gestionnaires). Les autres membres, dans l'aperçu rapide, peuvent seulement noter, réagir et commenter : pas de lien vers la photo ni d'accès à sa fiche (les 50 derniers commentaires sont affichés directement)
- Sur la fiche photo, l'encadré « Insérer dans un message du forum » donne trois BBCode et l'adresse de la page, chacun avec un bouton Copier :
  - `[portfolio=mini]12[/portfolio]` : vignette carrée
  - `[portfolio=medium]12[/portfolio]` (ou `[portfolio]12[/portfolio]`) : taille moyenne, 640 px maximum
  - `[portfolio=large]12[/portfolio]` : grande taille, toute la largeur du message
  - en dernier (v1.10.7), l'**adresse de la page** : un lien à cliquer, sans image, avec un avertissement pour ne pas la coller entre `[img]…[/img]`
- Le BBCode est créé à l'installation, avec un bouton dans l'éditeur des messages. L'image passe par le Portfolio : droits de l'album et filigrane s'appliquent, l'original n'est jamais exposé. Un lecteur qui n'a pas le droit de voir la photo voit une image neutre « Photo réservée »
- Après un changement d'adresse du forum : *ACP › Portfolio › Réglages › Installer / mettre à jour le BBCode*

### Corbeille et sécurité anti-erreur
- **Suppression par les membres (v1.10.5, revue en 1.10.6)** : la permission « Peut supprimer ses propres photos et albums » (*Permissions › Permissions des groupes / des utilisateurs › onglet Portfolio*) est à **Non par défaut** : les membres ne peuvent pas supprimer leurs photos ni leurs albums. L'accorder (Oui) aux groupes ou membres voulus ; « Jamais » interdit même si un autre groupe l'accorde. Sans elle, le membre peut toujours ajouter et déplacer ses photos. Administrateurs et modérateurs (permission « mettre à la corbeille toutes les photos ») ne sont pas concernés
- **Motif de suppression (v1.10.6)** : *ACP › Réglages › Sécurité › « Motif de suppression obligatoire »* (Oui par défaut). Le motif est demandé pour une photo comme pour une suppression en lot, et s'affiche dans la corbeille ; à Non, il reste facultatif
- **Supprimer une photo la met dans la corbeille** : rien n'est effacé tant qu'un administrateur n'a pas validé la suppression définitive
- **Le membre peut restaurer ses photos** (ou celles des albums qu'il gère) depuis sa corbeille. Si l'album d'origine a disparu, il choisit un nouvel album
- **Alerte en cas de suppression massive** : au-delà de N photos supprimées par un même membre en X secondes, les administrateurs reçoivent une notification (et un e-mail) et une ligne est ajoutée au journal d'administration. Le blocage des suppressions au-delà du seuil est possible en option
- L'ACP liste les suppressions des dernières 24 h par membre, filtre par membre, permet de restaurer ou de purger une sélection, ou de purger par ancienneté
- Purge automatique facultative (désactivée par défaut)

## Notifications des notes, commentaires et réactions (v1.10.0)

*ACP › Extensions › Portfolio › Notifications*. Le propriétaire d'une photo est prévenu **dans les notifications du forum** (la cloche en haut de page, pas par message privé) quand un autre membre la note, la commente ou y réagit (👍 ❤️ 😀…). La notification contient un **lien vers la photo** (vers le commentaire lui-même pour un commentaire).

- Trois interrupteurs : notes, commentaires, réactions (tous activés par défaut).
- **Une notification par action** : envoyée aussitôt (« Nouvelle note de Julien sur votre photo : ★★★★☆ »). Changer sa note ou sa réaction remplace la notification précédente au lieu d'en ajouter une ; la retirer, ou supprimer le commentaire, supprime la notification.
- **Notifications regroupées** : une seule notification par photo pour tout ce qui est arrivé depuis l'envoi précédent (« 2 nouvelles notes, 1 nouveau commentaire de Julien et 1 autre membre sur votre photo »), envoyée **toutes les minutes** (ou 5, 15, 30, 60). L'envoi passe par les tâches planifiées de phpBB, lancées lors des visites du forum ; la page indique les actions en attente et propose « Envoyer maintenant ». En repassant au mode « une par action », la file d'attente part aussitôt.
- Pas de notification pour ses propres actions. Chaque membre peut couper chaque type dans son panneau de l'utilisateur (*Préférences des notifications*, rubrique « Divers ») ; seuls les types utiles au mode choisi y sont proposés. Ces notifications n'envoient pas d'e-mail.
- Les actions sont gardées 30 jours dans la table `phpbb_portfolio_activity` (pour pouvoir remplacer ou retirer une notification), puis effacées automatiquement.

## Membres partis : rien n'est perdu (v1.9.0)

*ACP › Portfolio › Membres partis.*

Quand un membre quitte le forum, quelle qu'en soit la raison (suppression depuis l'ACP, élagage des membres inactifs, extension de nettoyage…) :
- **son nom est conservé** : ses photos, commentaires et notes restent signés « par … », en italique et sans lien vers un profil qui n'existe plus ;
- **ses albums sont rattachés au membre repreneur** choisi dans l'ACP (par exemple un compte d'équipe permanent ; à défaut, le premier fondateur). Ils sont rangés dans un album « Photos de … » à son nom, avec leurs sous-albums, pour ne pas se mélanger aux albums du repreneur, qui peut ensuite les gérer comme les siens ;
- les albums « Mes amis » deviennent **privés** (les amis du repreneur ne sont pas ceux du membre parti). Les partages explicites avec d'autres membres sont conservés ; l'album de rangement est aussi ouvert que le plus ouvert de ses albums ;
- les **anciens liens** vers son portfolio (`/portfolio/user/N`, y compris ceux de l'ancienne galerie) mènent à l'album « Photos de … ».

**Photos du membre parti** (réglage) : toujours conservées (par défaut) ; ou suivre le choix fait à la suppression (« Conserver les messages » les garde, « Supprimer les messages » les met à la corbeille) ; ou toujours à la corbeille. La corbeille reste validée ou restaurée par l'administration.

**Attribuer à un autre membre (v1.9.5)** : sur chaque ligne de la liste, un champ permet de choisir le membre qui reçoit (vide = le repreneur), et la case « Signer aussi les photos » attribue les photos du membre parti à ce membre : elles sont alors signées de son nom (pratique pour tout regrouper sous un compte du type « PhotoSansUser »).

**Photos déjà orphelines** (membres supprimés avant la 1.9.0, ou photos de l'ancienne galerie dont l'auteur était déjà parti) : la page liste les membres disparus qui possèdent encore des albums, avec leur nom retrouvé dans les tables de phpBB Gallery quand c'est possible, et un bouton « Rattacher au repreneur » (ou « Tout rattacher »). Leurs photos postées dans les albums d'autres membres restent en place, signées de leur nom.

## Pages Portfolio dans le forum (v1.8.0)

*ACP › Portfolio › Pages dans le forum › Créer une page.*

- **Contenu au choix** : photos les plus récentes, mieux notées, derniers commentaires (avec la vignette de la photo), photos au hasard, **albums de tous les membres** (v1.8.4 : cartes d'album avec leur propriétaire, les plus récemment mis à jour d'abord, avec pagination), ou **un album** (page spécifique, par exemple « Présentations » où chaque membre dépose sa photo, avec un bouton « Ajouter ma photo »)
- **Publication dans le forum** : un forum-lien est créé dans la catégorie, le forum ou le sous-forum choisi. Il apparaît dans la liste des forums comme n'importe quel forum
- **Qui voit la page** : les permissions de groupe de ce forum-lien (Lister, Lire), réglées comme pour tout forum. À la création, elles sont copiées depuis le forum parent ; un lien de l'ACP mène directement à leur réglage. Sans le droit « Lire », la page affiche « Vous n'êtes pas autorisé à afficher cette page »
- **Album protégé** : sur une page spécifique, les membres déposent des photos mais **ne peuvent ni les supprimer, ni les déplacer, ni supprimer l'album**. Seuls administrateurs et modérateurs le peuvent. Les sous-albums d'un album protégé le sont aussi. La protection s'active aussi depuis *Albums et droits*
- Dépublier une page retire son forum-lien ; supprimer une page ne touche ni à l'album ni aux photos

## Lien vers la galerie dans le profil et les messages (v1.5.0)
- **Profil** du membre : « Portfolio : N photo(s) dans N album(s) », avec un lien vers sa galerie
- **En bas du profil (v1.6.0)** : les dernières photos du membre (5 par défaut ; nombre choisi dans une liste dans l'ACP depuis la v1.9.6 : ne pas afficher, 3, 4, 5, 6, 8, 10, 12, 15 ou 20) et un bouton « Voir son portfolio »
- **Photos publiées ailleurs (v1.9.7)** : les photos qu'un membre poste dans un album public ou dans l'album d'un autre membre apparaissent aussi dans son portfolio (section « Photos de … dans les albums publics et partagés », avec pagination), en bas de son profil et dans ses compteurs. C'est un simple affichage : la photo reste dans son album d'origine, avec les mêmes droits (un visiteur ne voit que ce que l'album lui permet de voir). Sous chaque vignette, « dans *nom de l'album* » (avec la mention « public ») indique d'où elle vient ; sur le profil, le nom de l'album s'affiche sur la vignette
- **Mini-profil** à côté de chaque message : « Portfolio : N photos »
- Chacun s'active ou se désactive dans l'ACP (*Réglages › Réactions et profils*). La permission `u_portfolio_profile_link` choisit les groupes qui voient ces liens
- Seuls les albums que le visiteur a le droit de voir sont comptés : pas de lien vers une galerie entièrement privée

## Bascule depuis phpBB Gallery (v1.5.0)

*ACP › Extensions › Portfolio › Bascule phpBB Gallery*. Les deux extensions restent installées pendant la bascule.

**Ce qui est repris** pour chaque photo : l'original, le titre, la description, la date d'envoi, le nombre de vues, l'auteur, l'état (en attente de validation ou publiée), les **notes** (échelle de 1 à 10 de phpBB Gallery convertie en 1 à 5 étoiles) et les **commentaires** avec leur auteur et leur date. Les **albums et sous-albums** sont recréés avec la même arborescence et le même propriétaire, verrouillage compris. **Visibilité et téléchargement des albums créés (v1.9.8)** : par défaut, les albums repris sont réservés aux **membres inscrits** et le **téléchargement des originaux est interdit**. Deux réglages de la page de bascule permettent de choisir autrement : « Reprendre le réglage de phpBB Gallery » (tous → tout le monde, inscrits → membres inscrits, amis → mes amis) ou « Privé », et « Autoriser le téléchargement des originaux ». Le choix est retenu pour les lots suivants. Les albums publics de phpBB Gallery deviennent des albums publics du Portfolio. L'album « conteneur » de chaque membre devient sa page Portfolio.

**Au choix de l'administrateur** :
- **un membre**, **les membres d'un groupe** ou **tout le forum** ;
- **Simuler** d'abord : nombre de photos et d'albums concernés, membre par membre, sans rien modifier ;
- **Photos non copiées (v1.9.6)** : dans la liste, cochez des photos pour les **réessayer**, les **copier chez le membre repreneur** (album « Photos récupérées de la galerie », dont il devient l'auteur) ou les **abandonner** (elles sortent de la liste et ne sont plus retentées) ;
- **Fichiers introuvables (v1.9.1)** : le compteur a un lien « Voir la liste » qui donne, pour chaque photo non copiée, son titre, son membre, son album, le chemin attendu du fichier (`files/phpbbgallery/core/source/…`) et la raison (fichier absent du serveur, illisible, refusé à la conversion, photo supprimée de la galerie). Après avoir remis les fichiers, « Réessayer ces photos » les remet dans la file de bascule ;
- la bascule se fait par lots qui s'enchaînent tout seuls (reprise possible à tout moment, rien n'est copié deux fois). **Depuis la 1.9.2, chaque lot dure au plus une douzaine de secondes**, quelle que soit la taille des photos : le serveur web ne coupe plus la page (erreur 500) sur les grosses galeries. Laissez la page ouverte jusqu'au message « Bascule terminée » ; si elle est interrompue, relancez simplement « Basculer » ;
- **une erreur par photo (v1.9.3)** : pendant la bascule, chaque lot affiche les photos mises de côté avec leur raison et le message d'erreur exact ; à la fin, un lien mène à la liste complète. Si PHP s'arrête en pleine copie (mémoire épuisée…), l'erreur exacte et la photo concernée s'affichent en rouge en haut de la page, sans avoir à chercher dans les journaux du serveur. La page indique aussi la version du Portfolio, `memory_limit`, `max_execution_time` et la version de GD, et permet de régler le nombre de photos par lot (10 par défaut, descendre à 5 ou 1 en cas d'erreur 500) ;
- une photo trop lourde pour la mémoire PHP de l'hébergeur est mise de côté avec sa raison au lieu de faire planter le lot, et une photo sur laquelle le serveur s'est arrêté n'est plus retentée en boucle : elle apparaît dans « Voir la liste » (bouton « Réessayer ces photos ») ;
- retrait des données EXIF / GPS en option.

**phpBB Gallery n'est jamais modifiée** (copie uniquement). Le bouton « **Annuler toute la bascule** » retire du Portfolio tout ce qui a été copié.

**Les photos dans les messages restent affichées** :
- **Anciens liens** `/gallery/image/N`, `/gallery/image/N/mini|medium|source`, `/gallery/album/N` : redirection permanente (301) vers la photo, l'image ou l'album du Portfolio dès que l'élément est basculé, même avec phpBB Gallery encore active (bascule membre par membre). La galerie personnelle d'un membre mène à sa page Portfolio. Une fois phpBB Gallery désactivée, `/gallery` mène à l'accueil du Portfolio
- **BBCode `[image]N[/image]`** : un clic le fait passer par le Portfolio, **sans modifier les messages**. Une photo pas encore basculée reste affichée par phpBB Gallery tant qu'elle est installée. Quand on supprime les données de phpBB Gallery (ce qui efface ce BBCode), le Portfolio le **recrée aussitôt**. Une photo jamais basculée affiche « Photo indisponible » au lieu d'une image cassée
- **Réécriture des liens** des messages et signatures, en option : test sans modification, puis réécriture. Le texte d'origine est conservé et « **Annuler la réécriture** » remet tout exactement comme avant
- La table de correspondance `phpbb_portfolio_legacy` est gardée après la suppression de phpBB Gallery : c'est elle qui fait marcher les anciens liens

**Procédure conseillée** (rappelée dans l'ACP) :
1. Sauvegarde de la base de données du forum et, si besoin, du Portfolio
2. phpBB Gallery reste activée. Lancer « Simuler »
3. Basculer un membre de test, vérifier, puis un groupe ou tout le forum
4. Faire passer le BBCode `[image]` par le Portfolio
5. Facultatif : réécrire les liens des messages
6. Désactiver phpBB Gallery quelques jours pour vérifier, puis supprimer ses données

Ligne de commande pour les très grosses galeries : `php bin/phpbbcli.php portfolio:migrate-gallery [--simulate] [--user=pseudo | --group=ID] [--no-public] [--keep-exif] [--visibility=registered|keep|private] [--download] [--bbcode]` (sans `--visibility` ni `--download`, le réglage de l'ACP s'applique : membres inscrits, sans téléchargement).

## Export, sauvegarde et restauration (v1.4.0)

### Export ZIP pour les membres
- Bouton **« Exporter l'album (ZIP) »** (menu ⋯ de l'album, avec ses sous-albums) et **« Exporter mon portfolio »** (page du membre)
- L'archive contient les **photos originales rangées par album et sous-album** (`Vacances/Rome/001 - Colisée.jpg`), plus un dossier `data/` (titres, descriptions, commentaires, notes) qui permet de la réimporter
- **Lien de téléchargement unique à chaque export**, personnel (utilisable seulement avec le compte du membre ou par un admin), valable 24 h (réglable), supprimé automatiquement à expiration
- Limite réglable du nombre d'exports par 24 h. Permission `u_portfolio_export` par groupe
- Réservé aux albums dont le membre est propriétaire ou co-gestionnaire

### Sauvegarde complète (ACP › Portfolio › Sauvegarde)
- Une archive ZIP contient **toutes les données de l'extension** : albums, photos originales (corbeille comprise), droits de groupe, co-gestion, notes, commentaires, réglages et logo du filigrane
- Archives rangées dans `files/portfolio/backup/` (protégé), téléchargeables et supprimables depuis l'ACP
- Les vignettes ne sont pas archivées : elles sont recréées à la restauration, ce qui allège les sauvegardes

### Import et restauration
- Source : une archive du serveur (déposée par FTP si elle est volumineuse) ou un fichier envoyé depuis l'ACP
- **Ajouter** : le contenu est ajouté à l'existant avec de nouveaux identifiants. Fonctionne avec une sauvegarde complète ou un export de membre
- **Remplacer** : efface toutes les données du portfolio puis restaure la sauvegarde à l'identique (mêmes identifiants, donc mêmes liens). Restauration des réglages en option
- Les membres et groupes sont **retrouvés par leur nom** : on peut restaurer sur un autre forum. Un membre introuvable est remplacé par un « propriétaire de secours ». Une option permet de tout attribuer à un membre précis (réimport de l'export d'un membre)
- **Ligne de commande** pour les grosses galeries (pas de limite de temps) :
  - `php bin/phpbbcli.php portfolio:backup`
  - `php bin/phpbbcli.php portfolio:restore fichier.zip --mode=merge|replace [--owner=pseudo] [--force-owner=pseudo] [--config]`
- Nécessite l'extension PHP `zip` (présente chez la plupart des hébergeurs, dont o2switch et OVH)

## Droits par groupe

Tous les droits se règlent **par groupe** dans *ACP › Permissions › Permissions des groupes*, catégorie **Portfolio** :

| Permission | Rôle |
|---|---|
| `u_portfolio_view` | Voir le portfolio |
| `u_portfolio_create_album` | Créer ses albums et sous-albums |
| `u_portfolio_upload` | Envoyer des photos |
| `u_portfolio_delete` | Supprimer ses propres photos et albums (mise à la corbeille) — v1.10.5, **Non par défaut** depuis la 1.10.6 |
| `u_portfolio_noapprove` | Publier sans validation |
| `u_portfolio_rate` | Noter |
| `u_portfolio_comment` | Commenter |
| `u_portfolio_share` | Partager ses albums (co-gestion) |
| `u_portfolio_restore` | Restaurer depuis la corbeille |
| `u_portfolio_download` | Télécharger les originaux |
| `u_portfolio_unlimited` | Ignorer les quotas |
| `u_portfolio_export` | Exporter ses albums en ZIP |
| `u_portfolio_react` | Réagir aux photos (👍 ❤️ 😀…) |
| `u_portfolio_profile_link` | Voir le lien vers la galerie dans les profils et les messages |
| `u_portfolio_vis_groups` | Réserver un album à des groupes choisis (personne par défaut, les administrateurs l'ont toujours) |
| `m_portfolio_approve` / `_edit` / `_delete` / `_comments` | Modération |
| `a_portfolio_manage` | Réglages, albums publics, droits par album |
| `a_portfolio_trash` | Suppression définitive et restauration |

**Modifier plusieurs albums à la fois (v1.9.8)** — *ACP › Portfolio › Albums et droits* :
- cochez des albums (case en tête de colonne pour tout cocher) puis choisissez une nouvelle **visibilité** (tout le monde, membres inscrits, groupes choisis, amis du propriétaire, privé), le **téléchargement des originaux** (autorisé / interdit), le **filigrane** (appliqué / désactivé, v1.9.9), le **verrouillage** (verrouiller / déverrouiller) et/ou la **protection** (protéger / ne plus protéger), ces deux derniers depuis la v1.10.8 ; « Appliquer aussi à leurs sous-albums » est coché par défaut ; une confirmation indique le nombre d'albums touchés ;
- le **filtre « Visibilité »** n'affiche que les albums d'une visibilité donnée : par exemple, filtrer « Tout le monde », tout cocher et passer en « Membres inscrits » ;
- une colonne **Téléchargement** montre le réglage de chaque album ;
- « Amis du propriétaire » n'est pas appliqué aux albums publics (ils n'ont pas de propriétaire) ; un avertissement signale les albums passés en « Groupes choisis » sans droit de groupe ; chaque modification est inscrite au journal d'administration.

**Trier et retrouver les albums (v1.10.5)** — en tête de colonnes, cliquer sur **Album**, **Propriétaire** ou **Photos** trie la liste (un second clic inverse l'ordre ; les sous-albums sont alors affichés avec leur chemin « Parent › Sous-album »), et « Revenir à l'arborescence » rétablit l'ordre habituel. Le champ **« Albums d'un membre »**, avec le bouton « Rechercher un membre » de phpBB, n'affiche que les albums de ce membre. Tri, filtres et membre choisi sont conservés après une modification en nombre, un verrouillage ou une protection.

Il est aussi possible d'agir **album par album** (*ACP › Portfolio › Albums et droits › Droits des groupes*) : quels groupes peuvent voir, envoyer, noter et commenter dans un album donné, avec application possible à tous les sous-albums.

Valeurs posées à l'installation : les invités peuvent voir. Les membres inscrits et les rôles « Standard » et « Complet » reçoivent l'usage normal. Les rôles modérateur et administrateur reçoivent leurs droits.

## Base de données : tout est séparé du forum

L'extension utilise **ses propres tables** et ne modifie aucune table du forum :

| Table | Contenu |
|---|---|
| `phpbb_portfolio_config` | Réglages de l'extension (pas dans `phpbb_config`) |
| `phpbb_portfolio_albums` | Albums et sous-albums |
| `phpbb_portfolio_members` | Co-gestionnaires et contributeurs |
| `phpbb_portfolio_album_groups` | Droits par groupe et par album |
| `phpbb_portfolio_photos` | Photos (statut : en attente, publiée, corbeille) |
| `phpbb_portfolio_ratings` | Notes |
| `phpbb_portfolio_comments` | Commentaires |
| `phpbb_portfolio_exports` | Liens de téléchargement des exports ZIP |
| `phpbb_portfolio_reactions` | Réactions sur les photos |
| `phpbb_portfolio_legacy` | Correspondance phpBB Gallery → Portfolio (anciens liens) |
| `phpbb_portfolio_legacy_texts` | Texte d'origine des messages réécrits (annulation) |
| `phpbb_portfolio_activity` | Notes, commentaires et réactions à notifier (v1.10.0, gardés 30 jours) |
| `phpbb_portfolio_group_limits` | Quotas de photos et d'albums, validation obligatoire, par groupe (v1.10.3) |
| `phpbb_portfolio_pages` | Pages Portfolio publiées dans le forum |
| `phpbb_portfolio_departed` | Membres partis : nom conservé, repreneur, album de rangement |

phpBB impose de garder dans ses propres tables les permissions, le module ACP, les types de notification et les lignes de journal. Tout cela est retiré automatiquement par « Supprimer les données ».

Les fichiers sont rangés dans `files/portfolio/{source,medium,thumb}/` (plus `cache/` pour les images filigranées et `wm/` pour le logo), protégés par un `.htaccess` qui bloque l'accès direct.

## Installation

1. Copier le dossier dans `ext/verturin/portfolio/` (on doit obtenir `ext/verturin/portfolio/composer.json`).
2. *ACP › Personnaliser › Gérer les extensions* › **Portfolio** › Activer.
3. Vérifier que `files/portfolio/` est accessible en écriture (l'ACP affiche une alerte sinon).
4. Ajuster les permissions par groupe, puis les réglages dans *ACP › Extensions › Portfolio*.

**Désinstaller** : désactiver l'extension, puis « Supprimer les données ». Les tables, les réglages, les permissions **et les fichiers photo** sont alors effacés.

## Évènements pour les développeurs

- `verturin.portfolio.album_create_before` : `row`
- `verturin.portfolio.photo_add_before` : `row`, `album`
- `verturin.portfolio.photo_trash_before` : `ids`, `reason`

## Habillage de l'administration (v1.4.1)

Les 5 pages ACP (Réglages, Albums et droits, Filigrane, Sauvegarde, Corbeille) reprennent l'**ACP Skin de Chastity Tracker** : blocs blancs arrondis, titres de blocs en pastille, lignes de réglage aérées, tableaux arrondis, pastilles d'état et encadrés colorés.

- Feuille de style : `adm/style/portfolio_acp.css`. C'est l'ACP Skin d'origine, complété par quelques règles propres au Portfolio : cartes de statistiques, vignettes, matrice des droits, aperçu du filigrane, boutons `<button>`
- Chaque gabarit ACP commence par `{% INCLUDECSS '@verturin_portfolio/portfolio_acp.css' %}`, puis le repère `<span class="acp-skin" hidden="hidden"></span>` avant le `<h1>`. Le reste de l'administration du forum n'est pas modifié
- Classes utilisées : `skin-note` (+ `-warn` / `-error`), `skin-status` (+ `skin-ok` / `skin-failed` / `skin-pending` / `skin-info` / `skin-warn`), `skin-actions`, `skin-detail`, `table.zebra-table.skin-log`

## Modèles 100 % Twig

Tous les gabarits (`styles/all/template`, `adm/style`) sont écrits en syntaxe Twig, sans l'ancienne syntaxe `<!-- IF -->` / `<!-- BEGIN -->`, avec des macros communes (`portfolio_macros.html`). Le CSS et le JavaScript sont autonomes : ni jQuery, ni Font Awesome (icônes SVG intégrées). L'affichage est le même sous prosilver et sous les thèmes dérivés.

## Journal des versions

### 1.10.8 — 2026-10-19
- *ACP › Albums et droits* : la modification en nombre permet aussi de **verrouiller / déverrouiller** et de **protéger / ne plus protéger** plusieurs albums d'un coup (sous-albums compris si la case est cochée). Le message final signale les albums qui restent protégés par leur album parent. Filtres, tri et membre choisi restent en place après la confirmation.

### 1.10.7 — 2026-10-18
- Bloc « Insérer dans un message du forum » : les BBCode (vignette, moyenne, grande) passent en premier ; l'adresse de la page vient en dernier, renommée « Adresse de la page (lien à cliquer, sans image) », avec un avertissement : à coller telle quelle, jamais entre [img]…[/img].

### 1.10.6 — 2026-10-18
- La permission **« Peut supprimer ses propres photos et albums »** passe à **Non par défaut** : à accorder dans *Permissions* aux groupes ou membres voulus.
- **Motif de suppression** obligatoire ou facultatif au choix de l'administrateur (*Réglages › Sécurité*), **obligatoire par défaut**, y compris pour la mise à la corbeille en lot.
- Champ de profil laissé par phpBB Gallery (« GALLERY : USERS_PERSONAL_ALBUMS ») : traduit (« Portfolio : Voir ses photos ») tant que la galerie est désactivée, et son lien mène désormais au Portfolio du membre.

### 1.10.5 — 2026-10-18
- Nouvelle permission **« Peut supprimer ses propres photos et albums »** (`u_portfolio_delete`), accordée par défaut dans cette version (Non par défaut depuis la 1.10.6). À « Jamais », le membre ou le groupe ne peut plus supprimer ses photos ni ses albums ; il peut toujours en ajouter et les déplacer.
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
- **Notifications du forum** pour les notes, commentaires et réactions reçus sur ses photos, avec un lien vers la photo. Nouvelle page *ACP › Portfolio › Notifications* : chaque type activable séparément, envoi **une par action** (une nouvelle note ou réaction remplace la précédente, une note, réaction ou un commentaire retiré supprime la notification) ou **regroupé** toutes les 1, 5, 15, 30 ou 60 minutes (une notification par photo), file d'attente visible et « Envoyer maintenant ». Les membres règlent ces notifications dans leurs préférences.
- **Retrait du filigrane par le membre** : dans *Filigrane*, l'administration autorise des membres et/ou des groupes (avec un avertissement sur la sécurité et le vol de photos). Ces membres ont, dans chaque album et sous-album, la case « Afficher mes photos de cet album sans filigrane » (avec option pour les sous-albums). Ne vaut que pour leurs propres photos ; les autres albums gardent le filigrane sauf si leur propriétaire a fait le même choix. Liste des albums concernés et « Remettre le filigrane » dans l'ACP.
- Nouvelle table `phpbb_portfolio_activity`, nouvelle colonne `album_owner_no_wm` (conservée par la sauvegarde / restauration).

### 1.9.10 — 2026-10-12
- Filigrane en **mosaïque** : nouveau réglage « Fréquence des répétitions », de 1 (très espacé) à 10 (très serré), 5 par défaut (espacement d'avant). Affiché seulement quand la disposition « Mosaïque » est choisie ; changer la fréquence recalcule les images et force les navigateurs à les recharger.

### 1.9.9 — 2026-10-12
- **Filigrane désactivable par album** : *ACP › Portfolio › Filigrane › Albums sans filigrane*, choix de l'album dans une liste ; l'album et tous ses sous-albums (même créés plus tard) sont servis sans filigrane, grande taille, vignettes et images des messages comprises. Rétablissement en un clic, journal d'administration, rechargement immédiat des images dans les navigateurs.
- *Albums et droits* : option « Filigrane » (appliqué / désactivé) dans la modification en nombre, et étiquettes « Sans filigrane » / « Sans filigrane (album parent) » dans la liste.
- Sauvegarde / restauration : le réglage est conservé.

### 1.9.8 — 2026-10-11
- ACP › Albums et droits : **modification en nombre** de la visibilité et du téléchargement des originaux des albums cochés (sous-albums compris au choix), avec confirmation et journal ; filtre par visibilité ; colonne « Téléchargement ».
- Bascule phpBB Gallery : les albums créés sont **réservés aux membres inscrits** et **sans téléchargement des originaux** par défaut ; deux réglages permettent de reprendre la visibilité de phpBB Gallery (ou de tout passer en privé) et d'autoriser le téléchargement. Mêmes options en ligne de commande (`--visibility`, `--download`).

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
