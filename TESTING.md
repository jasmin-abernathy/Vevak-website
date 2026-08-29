# Espace privé de test VeVak

URL prévue : `https://vevak.lepotager.org/test/`.

Le dossier `test/` contient la page destinée aux premiers testeurs et son tutoriel d'installation Android hors Google Play.

## Principe de sécurité

Le dépôt GitHub étant public, **aucun mot de passe n'est stocké dans le code** et l'APK de test ne doit pas être commitée dans ce dépôt.

La protection doit être appliquée côté o2switch/cPanel avec l'outil **Confidentialité du répertoire**. Il s'agit d'une authentification HTTP gérée par le serveur avant que la page ou l'APK ne soient envoyées au navigateur.

Documentation o2switch :
https://faq.o2switch.fr/cpanel/fichiers/protection-repertoire-web/

## 1. Publier le dossier `test/`

Lors de la mise en ligne du site, envoyer aussi :

```text
test/
  index.html
  test.css
  files/
```

Le fichier `test/files/.gitignore` sert uniquement à empêcher qu'une APK soit ajoutée par erreur au dépôt public.

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

## 3. Déposer l'APK sur le serveur, pas sur GitHub

Après avoir compilé et testé une APK FOSS :

1. renommer la copie destinée aux testeurs en :

```text
VeVak-foss-test.apk
```

2. dans cPanel → Gestionnaire de fichiers, déposer ce fichier dans :

```text
<racine-vevak>/test/files/VeVak-foss-test.apk
```

3. vérifier que le bouton de la page `/test/` télécharge bien le fichier ;
4. vérifier dans une autre fenêtre privée que l'APK n'est pas accessible sans authentification.

URL directe protégée :

```text
https://vevak.lepotager.org/test/files/VeVak-foss-test.apk
```

## 4. À chaque nouvelle APK de test

Remplacer simplement le fichier serveur `VeVak-foss-test.apk` par la nouvelle version, après l'avoir testée localement.

Conserver le même nom permet de ne pas modifier la page HTML à chaque build.

Ne jamais placer une clé privée, un mot de passe, une APK de test ou un fichier `.htpasswd` dans le dépôt GitHub.

## 5. Indexation

La page contient :

```html
<meta name="robots" content="noindex,nofollow,noarchive">
```

et `robots.txt` interdit également l'exploration de `/test/`.

Ces deux mesures ne remplacent **pas** le mot de passe : elles servent seulement à éviter l'indexation. La vraie protection est l'authentification serveur de cPanel.

## 6. Installation Android

La page `/test/` explique aux testeurs :

- les avertissements possibles lors du téléchargement d'une APK ;
- l'autorisation temporaire **Installer des applis inconnues / Autoriser depuis cette source** ;
- le rôle de Google Play Protect ;
- qu'il ne faut pas désactiver Play Protect pour installer VeVak ;
- qu'un vrai signalement de danger doit interrompre le test ;
- la désactivation de l'autorisation d'installation inconnue après l'installation ;
- les permissions VeVak demandées au premier lancement ;
- un petit parcours de test terrain.

Les textes Android varient selon la version du système et le constructeur : la page présente donc les formulations probables sans prétendre reproduire mot pour mot tous les appareils.
