# Espace privé de test VeVak

URL prévue : `https://vevak.lepotager.org/test/`.

Le dossier `test/` contient la page destinée aux premiers testeurs et son tutoriel d'installation Android hors Google Play.

## Principe de sécurité

Le dépôt GitHub étant public, **aucun mot de passe ni APK de test n'est stocké dans le code**.

La page `/test/` doit rester protégée côté o2switch/cPanel avec l'outil **Confidentialité du répertoire**. Il s'agit d'une authentification HTTP gérée par le serveur avant que la page ne soit envoyée au navigateur.

Les APK de test sont distribuées séparément via le dossier Google Drive VeVak :

`https://drive.google.com/drive/folders/1CgzsBx0_Lh_TwcjFpFm5KwhYxupj5ZpW`

Attention : la protection cPanel de `/test/` ne protège pas automatiquement le dossier Drive. Les droits de partage Drive doivent donc être réglés selon le niveau d'accès souhaité.

Documentation o2switch :
https://faq.o2switch.fr/cpanel/fichiers/protection-repertoire-web/

## 1. Publier le dossier `test/`

Lors de la mise en ligne du site, envoyer :

```text
test/
  index.html
  test.css
  test.js
  retours/
```

Le sous-dossier `test/files/` n'est plus utilisé pour distribuer l'APK.

## 2. Protéger `/test/` avec un mot de passe

Dans cPanel :

1. ouvrir **Confidentialité du répertoire** ;
2. naviguer jusqu'à la racine de `vevak.lepotager.org` ;
3. choisir le dossier `test` avec l'action **Modifier** ;
4. cocher la protection par mot de passe ;
5. donner un nom explicite au répertoire, par exemple `VeVak - tests privés` ;
6. créer un utilisateur dédié avec un mot de passe long et unique ;
7. tester `https://vevak.lepotager.org/test/` dans une fenêtre de navigation privée.

Le navigateur doit demander l'identifiant et le mot de passe **avant** d'afficher la page.

Il est possible de créer plusieurs utilisateurs si l'on souhaite donner des identifiants distincts à plusieurs testeurs.

## 3. Publier une APK de test

Après avoir compilé et testé une APK FOSS :

1. déposer la nouvelle APK dans le dossier Drive VeVak ;
2. utiliser un nom explicite permettant d'identifier facilement la version la plus récente ;
3. vérifier les droits de partage du dossier et du fichier ;
4. ouvrir `/test/` et vérifier que le bouton **Ouvrir le téléchargement VeVak** mène bien au dossier Drive ;
5. depuis un téléphone Android, vérifier que le fichier `.apk` le plus récent est téléchargeable et installable.

Il n'est plus nécessaire de remplacer un fichier `VeVak-foss-test.apk` sur le serveur à chaque build.

Ne jamais placer une clé privée, un mot de passe, une APK de test ou un fichier `.htpasswd` dans le dépôt GitHub.

## 4. À chaque nouvelle APK de test

Ajouter la nouvelle version dans le même dossier Drive, après l'avoir testée localement.

Pour éviter les erreurs côté testeur :

- conserver un nom de fichier lisible avec la version ou la date ;
- retirer ou archiver les builds obsolètes si plusieurs fichiers deviennent ambigus ;
- vérifier que le fichier le plus récent est clairement identifiable.

La page `/test/` pointe vers le dossier plutôt que vers un fichier individuel, ce qui évite de devoir modifier le site à chaque nouvelle APK.

## 5. Indexation

La page contient :

```html
<meta name="robots" content="noindex,nofollow,noarchive">
```

et `robots.txt` interdit également l'exploration de `/test/`.

Ces deux mesures ne remplacent **pas** le mot de passe : elles servent seulement à éviter l'indexation. La vraie protection de la page est l'authentification serveur de cPanel.

## 6. Installation Android

La page `/test/` explique aux testeurs :

- comment ouvrir le dossier Drive et prendre l'APK la plus récente ;
- les avertissements possibles lors du téléchargement d'une APK ;
- l'autorisation temporaire **Installer des applis inconnues / Autoriser depuis cette source** ;
- le rôle de Google Play Protect ;
- qu'il ne faut pas désactiver Play Protect pour installer VeVak ;
- qu'un vrai signalement de danger doit interrompre le test ;
- la désactivation de l'autorisation d'installation inconnue après l'installation ;
- les permissions VeVak demandées au premier lancement ;
- un petit parcours de test terrain.

Les textes Android varient selon la version du système et le constructeur : la page présente donc les formulations probables sans prétendre reproduire mot pour mot tous les appareils.
