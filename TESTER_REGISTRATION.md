# Inscription des testeurs Google Play

Le formulaire public est intégré à l’accueil :

`https://vevak.lepotager.org/#devenir-testeur`

Il collecte l’adresse e-mail du compte Google utilisé dans Google Play, le modèle du téléphone, la version Android, la configuration SIM et l’accord explicite à participer au questionnaire de retour si la personne est retenue. Il n’inscrit pas automatiquement la personne dans Play Console et n’envoie aucun e-mail.

## Stockage

Les demandes sont enregistrées côté serveur hors de la racine publique, par défaut dans :

`$HOME/.vevak-private/`

Le répertoire contient :

- `testers.json` : e-mail, date UTC de première demande, dernière demande et version du texte d’accord ;
- `tester-rate-limits.json` : empreintes techniques temporaires utilisées pour la limitation de fréquence ;
- `tester-rate-secret` : secret local servant à pseudonymiser l’adresse IP avant stockage dans le fichier de limitation.

Aucun de ces fichiers ne doit être ajouté à Git, copié dans le document root ou servi par le site. Si l’hébergement ne fournit pas une variable `HOME` correcte, définir `VEVAK_TESTERS_STORAGE_DIR` vers un répertoire privé hors du document root.

## Administration

L’interface privée est :

`https://vevak.lepotager.org/test/admin-testers.php`

Elle réutilise la protection cPanel déjà appliquée au dossier `/test/`. Le script refuse aussi l’accès si le serveur ne lui transmet pas un utilisateur authentifié (`REMOTE_USER` ou `PHP_AUTH_USER`).

L’interface permet :

- de consulter les demandes ;
- de supprimer une inscription individuellement avec protection CSRF ;
- d’exporter une liste pour Google Play ;
- d’exporter une version destinée à un tableur.

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

Le fonctionnement est distinct de la protection cPanel de `/test/` :

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
- **un commentaire libre facultatif pour chaque question**.

Les commentaires ne doivent contenir aucune donnée sensible (coordonnées GPS, numéros, phrase-clé, SSID/BSSID ou identité d'un contact).

### Export des réponses

`/test/admin-testers.php?export=feedback` produit `vevak-retours-test.csv`.

Il contient :

- e-mail du testeur ;
- modèle / Android / SIM ;
- dates de mise à jour et d'envoi ;
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
