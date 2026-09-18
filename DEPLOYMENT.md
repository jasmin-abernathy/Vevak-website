# Déploiement de vevak.lepotager.org sur o2switch

État vérifié le 18 septembre 2026.

La production utilise désormais un **déploiement tiré depuis o2switch** :

```text
GitHub / main
      ↓ clé SSH dédiée au dépôt
~/repositories/Vevak-website
      ↓ cron + fast-forward uniquement
      ↓ staging des seuls fichiers publics
      ↓ rsync avec backup et exclusions
~/public_html/VeVak
      ↓
https://vevak.lepotager.org
```

Le serveur n’édite pas le code et ne pousse rien vers GitHub. La branche `main` reste la source de vérité.

## Pourquoi ce mécanisme

Le site était auparavant déployé par un script serveur très simple utilisant `git pull --ff-only` puis `rsync --delete`. Il fonctionnait, mais ne sauvegardait pas les fichiers remplacés et pouvait supprimer par erreur un élément live oublié dans les exclusions.

Le script versionné `ops/deploy-o2switch-pull.sh` ajoute donc :

- un verrou `flock` ;
- un refus des modifications locales suivies ;
- `fetch` + fast-forward uniquement ;
- un état du dernier commit déployé ;
- un staging contenant uniquement les éléments web réellement publiables ;
- une sauvegarde hors du DocumentRoot des fichiers remplacés/supprimés ;
- des exclusions pour les protections et composants gérés directement sur le serveur ;
- un contrôle HTTP après déploiement ;
- un log lisible même lorsqu’il n’y a aucun nouveau commit.

Aucun ancien backup n’est supprimé automatiquement pendant la phase de stabilisation.

## Fichiers réellement publiés

Le staging ne prend que :

- `index.html` ;
- `.nojekyll` ;
- `assets/` ;
- `en/` ;
- `soutenir/` ;
- `test/` ;
- `robots.txt` ;
- `sitemap.xml`.

Les fichiers de documentation du dépôt ne sont donc plus copiés dans la racine publique.

## Éléments serveur préservés

Le rsync exclut notamment :

- tous les `.htaccess` ;
- tous les `.htpasswd` ;
- `.well-known/` ;
- `api/` ;
- `cgi-bin/` ;
- `test/files/`.

Cela protège notamment le backend Stancer et les protections cPanel.

## SSH dédié

Le clone o2switch doit utiliser un alias SSH propre :

```ssh
Host github-vevak
    HostName github.com
    User git
    IdentityFile ~/.ssh/vevak_github_deploy
    IdentitiesOnly yes
```

Puis :

```bash
git -C "$HOME/repositories/Vevak-website" remote set-url origin \
  git@github-vevak:jasmin-abernathy/Vevak-website.git
```

Avant la bascule, tester la clé sans modifier le dépôt :

```bash
GIT_SSH_COMMAND="ssh -i $HOME/.ssh/vevak_github_deploy -o IdentitiesOnly=yes -o BatchMode=yes" \
git ls-remote git@github.com:jasmin-abernathy/Vevak-website.git HEAD
```

## Premier passage

Récupérer d’abord le nouveau script :

```bash
git -C "$HOME/repositories/Vevak-website" fetch origin main
git -C "$HOME/repositories/Vevak-website" merge --ff-only origin/main
```

Puis lancer manuellement :

```bash
bash "$HOME/repositories/Vevak-website/ops/deploy-o2switch-pull.sh"
```

Contrôler ensuite :

```bash
tail -50 "$HOME/logs/vevak-deploy.log"
curl -LsS -o /dev/null -w 'HTTP %{http_code}\n' https://vevak.lepotager.org/
curl -LsS -o /dev/null -w 'HTTP %{http_code}\n' https://vevak.lepotager.org/soutenir/
```

Le dossier des backups est :

```text
~/backups/vevak-site/
```

## Cron

Une fois le passage manuel validé, remplacer l’ancien appel à `~/bin/deploy-vevak.sh` par le script versionné :

```cron
35 * * * * /bin/bash /home/sc1leja3715/repositories/Vevak-website/ops/deploy-o2switch-pull.sh >> /home/sc1leja3715/logs/vevak-cron.log 2>&1
```

Le script maintient aussi son propre journal dans `~/logs/vevak-deploy.log`.

## Retour arrière

Les fichiers remplacés ou supprimés par rsync sont copiés dans un dossier horodaté sous `~/backups/vevak-site/`.

En cas de problème applicatif, la méthode normale reste :

1. revert/correctif sur GitHub ;
2. nouveau commit sur `main` ;
3. nouveau passage du script.

Les backups serveur servent de filet de sécurité supplémentaire, pas de source de vérité.

## Ancien workflow GitHub Actions

Le dépôt contient encore un workflow de déploiement o2switch par GitHub Actions. Il avait été préparé pour une autre architecture et ne doit **pas** être activé en parallèle du cron pull.

Tant que le déploiement tiré depuis o2switch est le mécanisme de production, ne renseigne pas les secrets o2switch de ce workflow et n’utilise pas deux mécanismes de publication concurrents.

## APK

Le site web et l’APK restent séparés. Le déploiement décrit ici ne publie pas l’application Android.
