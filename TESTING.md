# Espace privé de test VeVak

URL prévue : `https://vevak.lepotager.org/test/`.

Le dossier `test/` contient la page destinée aux premiers testeurs et son tutoriel d'installation Android hors Google Play.

## Principe de sécurité

Le dépôt GitHub étant public, **aucun mot de passe ni secret n'est stocké dans le code**.

La page `/test/` doit rester protégée côté o2switch/cPanel avec l'outil **Confidentialité du répertoire**. Il s'agit d'une authentification HTTP gérée par le serveur avant que la page ne soit envoyée au navigateur.

L'APK de test est désormais distribuée automatiquement depuis le dépôt GitHub public de VeVak, via une release roulante portant le tag `beta` :

`https://github.com/jasmin-abernathy/vevak/releases/download/beta/VeVak-foss-test.apk`

La protection cPanel de `/test/` ne protège donc pas l'APK elle-même : le dépôt VeVak et sa release bêta sont publics. La page test reste privée pour le parcours guidé, les consignes et les questionnaires.

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

Le sous-dossier `test/files/` n'est pas utilisé pour distribuer l'APK.

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

La publication est automatique depuis `jasmin-abernathy/vevak`.

À chaque changement Android poussé sur `main` :

1. GitHub Actions exécute les vérifications statiques de confidentialité et d'écoconception ;
2. les tests unitaires FOSS sont exécutés ;
3. la variante FOSS debug est compilée puis passée au lint ;
4. les tests, le build et le lint de la variante Play sont également exécutés ;
5. si tout réussit, l'APK FOSS est conservée comme artefact GitHub Actions ;
6. un job séparé avec droit d'écriture publie cette même APK dans la release roulante `beta` sous le nom stable `VeVak-foss-test.apk` ;
7. un fichier `VeVak-foss-test.apk.sha256` est publié avec elle.

La release n'est donc mise à jour **qu'après réussite complète de la CI**.

Le lien utilisé par `/test/` reste toujours le même :

`https://github.com/jasmin-abernathy/vevak/releases/download/beta/VeVak-foss-test.apk`

Aucun transfert manuel vers Drive, le serveur Web ou un autre hébergement n'est nécessaire.

## 4. À chaque nouvelle APK de test

Il n'y a plus d'opération de fichier à effectuer manuellement.

Le cycle normal devient :

```text
modification du code
        ↓
push sur main
        ↓
GitHub Actions
        ↓
tests + builds + lints OK
        ↓
release GitHub beta mise à jour
        ↓
/test/ télécharge automatiquement la nouvelle APK
```

Avant de prévenir les testeurs d'une nouvelle bêta, vérifier simplement que la dernière exécution GitHub Actions est verte et que la release `beta` affiche bien le nouvel APK.

Le tag `beta` est volontairement roulant : il pointe vers le commit correspondant au dernier build publié. Les versions stables futures devront utiliser des tags/versionnements distincts.

## 5. Indexation

La page contient :

```html
<meta name="robots" content="noindex,nofollow,noarchive">
```

et `robots.txt` interdit également l'exploration de `/test/`.

Ces deux mesures ne remplacent **pas** le mot de passe : elles servent seulement à éviter l'indexation. La vraie protection de la page est l'authentification serveur de cPanel.

## 6. Installation Android

La page `/test/` explique aux testeurs :

- comment télécharger directement l'APK bêta depuis GitHub ;
- les avertissements possibles lors du téléchargement d'une APK ;
- l'autorisation temporaire **Installer des applis inconnues / Autoriser depuis cette source** ;
- le rôle de Google Play Protect ;
- qu'il ne faut pas désactiver Play Protect pour installer VeVak ;
- qu'un vrai signalement de danger doit interrompre le test ;
- la désactivation de l'autorisation d'installation inconnue après l'installation ;
- les permissions VeVak demandées au premier lancement ;
- un petit parcours de test terrain.

Les textes Android varient selon la version du système et le constructeur : la page présente donc les formulations probables sans prétendre reproduire mot pour mot tous les appareils.

## 7. Ce qui reste privé ou public

- `/test/` : privé côté serveur ;
- questionnaires et consignes : accessibles seulement via l'espace test protégé ;
- code source VeVak : public ;
- release `beta` et APK FOSS associée : publiques ;
- secrets, mots de passe, clés de signature privées : jamais commités ni publiés.

La bêta publiée automatiquement est actuellement une build `fossDebug`. Pour une distribution publique stable, une procédure séparée de signature/release reproductible devra être utilisée.
