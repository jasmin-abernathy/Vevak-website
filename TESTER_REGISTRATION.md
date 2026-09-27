# Inscription des testeurs Google Play

Le formulaire public est intégré à l’accueil :

`https://vevak.lepotager.org/#devenir-testeur`

Il collecte uniquement l’adresse e-mail du compte Google utilisé dans Google Play, après accord explicite. Il n’inscrit pas automatiquement la personne dans Play Console et n’envoie aucun e-mail.

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
4. la personne ouvre `/retours/`, saisit son e-mail autorisé et crée elle-même un mot de passe d'au moins 12 caractères ;
5. les connexions suivantes utilisent e-mail + mot de passe ;
6. le questionnaire sauvegarde les réponses côté serveur et permet une reprise après reconnexion.

Le mot de passe est enregistré uniquement avec `password_hash()`. Aucun mot de passe en clair n'est conservé.

L'administration peut :

- autoriser/suspendre l'accès aux retours ;
- voir si un mot de passe a déjà été créé ;
- voir si un questionnaire est en brouillon ou envoyé ;
- réinitialiser le mot de passe (suppression du hash : le testeur en crée alors un nouveau) ;
- exporter les réponses ;
- supprimer l'inscription, ce qui supprime également le compte et les réponses.

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

- `tester-feedback-accounts.json` : hashes des mots de passe ;
- `tester-feedback-answers.json` : réponses et dates ;
- `tester-feedback-rate-limits.json` : empreintes temporaires anti-bruteforce.

La suppression d'un testeur nettoie ses entrées dans les deux premiers fichiers.
