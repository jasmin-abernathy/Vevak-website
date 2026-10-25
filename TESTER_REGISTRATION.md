# Inscription des testeurs Google Play

Le formulaire public est intégré à l’accueil :

`https://vevak.lepotager.org/#devenir-testeur`

Il collecte l’adresse e-mail du compte Google utilisé dans Google Play, le modèle du téléphone, la version Android, la configuration SIM et l’accord explicite à participer au questionnaire de retour si la personne est retenue. Il n’inscrit pas automatiquement la personne dans Play Console. Lors d’une **nouvelle candidature**, une notification sans données personnelles du candidat est envoyée à l’adresse d’administration du site (par défaut `contact@lepotager.org`). Aucune notification n’est envoyée pour une inscription déjà existante. L’inscription est conservée même si le transport du message échoue. À ce jour, aucun accusé de réception par e-mail n'est envoyé automatiquement à la personne candidate : seule une confirmation à l'écran est présentée ; la prise de contact après sélection reste manuelle.

## Stockage

Les demandes sont enregistrées côté serveur hors de la racine publique, par défaut dans :

`$HOME/.vevak-private/`

Le répertoire contient :

- `testers.json` : e-mail, date UTC de première demande, dernière demande et version du texte d’accord ;
- `tester-rate-limits.json` : empreintes techniques temporaires utilisées pour la limitation de fréquence ;
- `tester-rate-secret` : secret local servant à pseudonymiser l’adresse IP avant stockage dans le fichier de limitation.

Aucun de ces fichiers ne doit être ajouté à Git, copié dans le document root ou servi par le site. Si l’hébergement ne fournit pas une variable `HOME` correcte, définir `VEVAK_TESTERS_STORAGE_DIR` vers un répertoire privé hors du document root.

## Notifications d'inscription

Les nouvelles candidatures déclenchent un message depuis `contact@lepotager.org` vers `contact@lepotager.org` par défaut, avec seulement un lien vers l'interface protégée. Le destinataire peut être configuré avec la variable serveur `VEVAK_TESTERS_NOTIFY_TO` (adresse unique valide). En cas d'échec de transport, PHP journalise une erreur sans e-mail du candidat. Un message accepté par sendmail ne garantit pas sa livraison : vérifier la boîte destinataire et les logs o2switch.

La variable `VEVAK_TESTERS_NOTIFY_DISABLED=1` désactive explicitement cet envoi, uniquement à utiliser pour les tests sans e-mail externe. Les réinscriptions d'une adresse existante ne déclenchent pas de nouvel envoi. Les candidatures antérieures à la mise à jour ne sont pas notifiées rétroactivement et restent consultables dans l'administration.

## Administration

L’interface privée est :

`https://vevak.lepotager.org/test/admin-testers.php`

L'interface est désormais protégée par **mot de passe puis code à 8 chiffres envoyé par e-mail** sur l'unique boîte administrateur fixée par le serveur. Il n'y a plus de dépendance PHP à `REMOTE_USER` ou `PHP_AUTH_USER`. L'entrée conviviale est `https://vevak.lepotager.org/test/` et la connexion se fait sur `/test/login.php`.

**Attention :** la protection cPanel existante est gérée sur l'hébergement et n'est pas retirée par un commit Git. Après publication et vérification de la nouvelle connexion, désactiver l'ancienne « Confidentialité des répertoires » pour `/test/` dans cPanel, en veillant à ne pas supprimer d'autres protections nécessaires aux anciens fichiers. Vérifier qu'un visiteur non connecté est redirigé depuis l'administration et depuis chaque export vers la page de connexion, sans fuite de CSV.

L’interface permet :

- de consulter les demandes ;
- de supprimer une inscription individuellement avec protection CSRF ;
- d’exporter une liste pour Google Play ;
- d’exporter une version destinée à un tableur ;
- de se déconnecter et de **modifier son mot de passe** en confirmant le mot de passe précédent.

### Confirmer manuellement les inscriptions déjà reçues

L’administration `/test/admin-testers.php` comporte une section **« Confirmer la réception des inscriptions »** au-dessus du tableau :

- ouvrir l’aperçu du message, qui indique que la candidature est enregistrée et que les instructions pour télécharger et tester l’application seront transmises ultérieurement lorsque l’accès sera prêt ;
- cocher la confirmation explicite de l’envoi ;
- choisir **« Envoyer aux inscriptions en attente »** pour les 20 premières candidatures non encore averties (ordre de création), ou cocher certaines lignes dans le tableau et utiliser **« Envoyer aux personnes sélectionnées »** ;
- le site expédie un message indépendant par adresse depuis `contact@lepotager.org`, sans dévoiler d’autres destinataires ;
- après acceptation par le transport PHP/mail, `testers.json` mémorise `receipt_sent_at` et `receipt_version`. La ligne indique la date UTC. Les adresses déjà traitées sont ignorées, même en cas de double clic ou de nouvelle inscription du même compte ;
- si un envoi échoue, le destinataire reste en attente pour une nouvelle tentative. Le bilan affiche les envois acceptés, les échecs et le nombre restant, mais l’acceptation par sendmail **ne prouve pas** la livraison en boîte de réception.

L’outil **n’envoie aucun mail automatiquement lors d’une nouvelle candidature** et n’envoie aucun accusé rétroactif tant que l’administrateur n’a pas explicitement lancé l’opération. Les messages sont réservés aux adresses d’inscriptions avec consentement enregistré. Aucun service de mailing tiers ni donnée de contact publiée dans Git.

Le traitement est limité à **20 candidatures par action** afin de limiter les blocages en hébergement mutualisé. Pour poursuivre, relancer l’envoi sur les candidatures restant en attente ; ne pas supprimer le fichier privé entre deux envois. L’envoi et son historique sont couverts par `ops/test-tester-receipts.php` avec un expéditeur simulé en CI et avant déploiement. La livraison réelle doit être contrôlée séparément par un envoi volontaire et les journaux de l’hébergeur.

### Première activation et sécurité du compte

- La boîte unique est déterminée par `VEVAK_ADMIN_EMAIL` (ou, par défaut, `contact@lepotager.org`). Il faut impérativement pouvoir recevoir ses messages avant de désactiver cPanel.
- Première visite sur `/test/` : **Recevoir mon code d'activation**, puis saisir le code e-mail et choisir un mot de passe d'au moins 12 caractères (72 octets maximum). Un visiteur ne peut pas choisir librement l'adresse administrateur.
- Connexions suivantes : mot de passe, puis code à usage unique valide 10 minutes ; cinq erreurs maximum par code, avec limitation des demandes ; la session expire après 30 minutes d'inactivité ou 12 h au plus.
- « Mot de passe oublié ? » permet de le réinitialiser uniquement depuis la boîte administrateur via un code temporaire. L'option dans l'espace connecté demande le mot de passe actuel.
- Les empreintes du mot de passe, des codes et les données de contrôle sont conservées sous `~/.vevak-private/admin-auth.json` et `admin-auth-rates.json`, jamais dans Git ou le répertoire public. Le changement de mot de passe révoque toutes les autres sessions. Les mails utilisent le transport local PHP/sendmail o2switch ; contrôler sa délivrabilité.
- Les tests automatisés utilisent un transport e-mail simulé et un stockage temporaire, sans envoyer d'e-mail réel.

### Passage depuis cPanel

1. Laisser temporairement en place la protection cPanel et synchroniser les nouveaux fichiers.
2. Vérifier les tests du dépôt et l'existence de `/test/login.php` sous l'ancienne protection.
3. Configurer/vérifier le destinataire `VEVAK_ADMIN_EMAIL`, notamment la réception depuis `contact@lepotager.org`.
4. Retirer **uniquement** la protection cPanel du dossier `/test/` dans cPanel, puis activer le compte avec le code envoyé par e-mail.
5. Contrôler à partir d'une navigation privée les redirections de `/test/admin-testers.php` et de `?export=play`, et s'assurer que `/test/files/` et les anciens chemins ne contiennent pas de données privées exposées.

Il ne faut pas copier les inscriptions, les codes, les secrets ou les anciens mots de passe dans Git pour effectuer cette transition.

### Export Google Play

`?export=play` produit `vevak-google-play-testers.csv` avec **un e-mail par ligne**, sans en-tête, sans virgule et sans BOM UTF-8. L’adresse est conservée telle qu’elle a été fournie.

### Export tableur

`?export=spreadsheet` protège les adresses dont le premier caractère pourrait être interprété comme une formule. Cet export ne doit pas être utilisé pour Play Console car la protection modifie volontairement l’affichage de ces adresses.

## Anti-abus et confidentialité

- piège à robots invisible aux utilisateurs ;
- limitation de fréquence côté serveur ;
- aucune adresse IP brute n’est conservée par l’application ;
- même confirmation publique pour une nouvelle demande et un doublon ;
- aucun e-mail n’est placé dans l’URL ;
- le formulaire ne demande jamais de mot de passe, code Google, nom ou téléphone ;
- le mandat d’accès à Google Play reste une étape distincte et manuelle dans Play Console.

## Contrôles après déploiement

1. Vérifier que l’accueil affiche `#devenir-testeur` et qu’aucun téléchargement APK n’est proposé sur l’accueil FR ou EN.
2. Envoyer une requête invalide à `/assets/tester-submit.php` et vérifier qu’elle est traitée par PHP sans créer d’inscription.
3. Vérifier qu’une adresse non-Gmail valide est acceptée avec consentement.
4. Vérifier qu’une adresse invalide et un accord manquant produisent les messages attendus.
5. Réenvoyer la même adresse et vérifier qu’une seule ligne reste dans l’administration.
6. Tester le formulaire sans JavaScript.
7. Vérifier que `/test/admin-testers.php` reste protégé sans authentification.
8. Supprimer les données fictives depuis l’administration.
9. Vérifier l’export Google Play avant import dans Play Console.

## Conservation

La politique publique ne fixe pas une durée arbitraire. Les demandes doivent être supprimées lorsqu’elles ne sont plus nécessaires à la gestion du panel ou lorsqu’une personne demande leur suppression, sous réserve des obligations légales applicables. Définir une durée opérationnelle précise avant la fin de la campagne et l’aligner avec le texte public.


## Profil du téléphone et engagement de retour

À partir du 27 septembre 2026, l'inscription demande également :

- marque et modèle du téléphone ;
- version Android ;
- configuration SIM (une SIM/eSIM, deux SIM/eSIM, ou inconnue) ;
- accord obligatoire pour participer ensuite au questionnaire de retour si la personne est retenue.

Ces champs servent à interpréter les différences de comportement Android/constructeur et ne sont jamais inclus dans l'export Google Play.

## Espace privé de retours

URL :

`https://vevak.lepotager.org/retours/`

Le fonctionnement est distinct de l'authentification administrateur de `/test/` :

1. la personne s'inscrit sur le formulaire public et accepte le questionnaire ;
2. Jasmin la retient comme testeur ;
3. dans `/test/admin-testers.php`, utiliser **Autoriser les retours** ;
4. la personne ouvre `/retours/` et demande un code à son adresse autorisée ;
5. elle saisit les 8 chiffres reçus par e-mail (validité dix minutes, une seule utilisation) ;
6. le questionnaire retrouve les réponses déjà enregistrées pour cette adresse.

Les anciens mots de passe et liens d’activation ne permettent plus de se connecter. Les anciens liens mènent au nouveau formulaire, sans authentifier leur porteur. Les réponses restent inchangées.

L'administration autorise ou suspend l'accès, consulte l'état des réponses et permet leur export ou la suppression du testeur. Elle n'a plus à créer de lien ni réinitialiser de mot de passe.

### Envoi des codes

Le serveur o2switch utilise PHP `mail()` et son transport local sendmail, avec expéditeur/enveloppe `contact@lepotager.org`. Aucun secret de messagerie n'est placé dans Git. Une erreur de transport est journalisée sans adresse ni code. L'acceptation par sendmail ne garantit pas la réception dans la boîte finale.

La réponse publique est identique pour une adresse autorisée ou inconnue. Seules les adresses autorisées avec consentement reçoivent un message. Délai minimal de 60 secondes entre deux envois, maximum 5 demandes par adresse et 20 par IP en 15 minutes. Chaque code accepte au plus cinq essais, complétés par des limites indépendantes par adresse et IP. Renvoi, vérification et consommation sont protégés par verrou exclusif ; les empreintes sont des HMAC avec secret local privé. Un échec de transport conserve le code précédent.

Le test `ops/test-feedback-code.php`, exécuté en CI et avant chaque déploiement avec un répertoire privé temporaire, ne transmet aucun e-mail réel.

## Questionnaire

Le questionnaire reprend le principe des questionnaires Jardinier/TDAH :

- mobile-first ;
- une question par écran ;
- 12 questions courtes ;
- choix simples avec possibilité `Non testé` lorsque pertinent ;
- sauvegarde automatique ;
- reprise après déconnexion/reconnexion ;
- **un commentaire libre facultatif pour chaque question** ;
- une identification de la version réellement testée : **FOSS (APK GitHub)**, **Google Play** ou **Je ne sais pas**, plus numéro de version et révision du build facultatifs. La variante est demandée avant l'envoi final, pas pour les sauvegardes automatiques.

Les commentaires ne doivent contenir aucune donnée sensible (coordonnées GPS, numéros, phrase-clé, SSID/BSSID ou identité d'un contact).

### Export des réponses

`/test/admin-testers.php?export=feedback` produit `vevak-retours-test.csv`.

Il contient :

- e-mail du testeur ;
- modèle / Android / SIM ;
- dates de mise à jour et d'envoi ;
- `tested_variant`, `tested_version` et `tested_revision` pour distinguer les APK FOSS de Google Play ;
- réponse à chaque question ;
- commentaire libre associé à chaque question.

Cet export n'est pas destiné à Google Play.

## Stockage privé supplémentaire

Toujours sous `~/.vevak-private/` (ou `VEVAK_TESTERS_STORAGE_DIR` en test) :

- `testers.json` : contient aussi l’empreinte HMAC du dernier code, son expiration et le compteur d’essais ;
- `tester-feedback-answers.json` : réponses et dates ;
- `tester-feedback-rate-limits.json` : empreintes temporaires anti-abus ;
- `tester-feedback-accounts.json` : ancien fichier de mots de passe, désormais inutilisé pour l’authentification. Ses entrées sont supprimées lors de la suppression d’un testeur ; le fichier historique peut être vidé lors de la migration après sauvegarde privée.

La suppression d'un testeur retire également son code et ses réponses. La page applique `no-referrer` et `no-store`.
