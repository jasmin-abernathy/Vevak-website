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
- `retours/` ;
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

## Accès Git au dépôt

Le dépôt `Vevak-website` est public. Pour le clone de production o2switch, le remote canonique utilise donc HTTPS en lecture seule :

```bash
git -C "$HOME/repositories/Vevak-website" remote set-url origin \
  https://github.com/jasmin-abernathy/Vevak-website.git
```

Cette configuration évite de dépendre d'une clé SSH pour un dépôt qui n'en a pas besoin. Le serveur de production ne pousse jamais vers GitHub.

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


## Espace retours testeurs

Le dossier public `retours/` est maintenant inclus dans le staging du déploiement pull o2switch.

Il contient uniquement le code du portail et du questionnaire. Les codes de connexion (empreintes uniquement) et réponses ne vivent jamais dans le DocumentRoot : ils restent sous `~/.vevak-private/` via `assets/tester-storage.php`.

Contrôle après déploiement :

```bash
curl -LsS https://vevak.lepotager.org/retours/ | grep -q 'Questionnaire de retour'
```

Le résultat doit être la page de connexion par code e-mail, jamais les réponses d'un testeur.


## Communiqué de presse — octobre 2026

Le communiqué est disponible dans `presse/index.html` et le PDF dans `assets/vevak-communique-presse-2026-10-11.pdf`. L'URL du PDF GitHub (accessible même si le cron o2switch n'a pas encore synchronisé) est `https://raw.githubusercontent.com/jasmin-abernathy/Vevak-website/main/assets/vevak-communique-presse-2026-10-11.pdf`.

Si la page `/presse/` n'apparaît pas malgré des modifications habituelles du site, vérifier que la crontab invoque bien `ops/deploy-o2switch-pull.sh` et pas l'ancien `~/bin/deploy-vevak.sh` : une ancienne liste de fichiers autorisés peut omettre `presse/`. Consulter les logs `~/logs/vevak-cron.log` et `~/logs/vevak-deploy.log`. Ne pas déduire un déploiement du simple statut vert GitHub Actions, car le workflow historique est volontairement inactif sans ses secrets.


## Version PDF à URL distincte — 11 octobre 2026

Un nouveau chemin `assets/vevak-communique-presse-2026-10-11-v2.pdf` reprend exactement le PDF éditorial de 107 233 octets (blob Git `9f3a37281ebdc49063db24ef8da80730a7bb0c4a`). La page `presse/index.html` pointe désormais vers ce nouveau nom pour éviter toute ancienne copie en cache de l'URL précédente. L'ancien nom reste disponible pour compatibilité mais n'est plus la cible affichée.

Le script de déploiement vérifie maintenant que le PDF dans le DocumentRoot a les mêmes octets que celui du dépôt, puis que la réponse HTTP publique correspond aussi, y compris lorsqu'aucun nouveau commit n'est détecté. En cas de divergence, consulter `~/logs/vevak-deploy.log` : la cause n'est plus imputée aveuglément à la synchronisation ; il peut s'agir du cache ou d'une copie concurrente.


## Migration de l'authentification de /test/ — octobre 2026

L'espace administrateur est désormais défendu par un mot de passe et un code
unique reçu sur la boîte `VEVAK_ADMIN_EMAIL` (par défaut `contact@lepotager.org`).
Le dossier `/test/` redirige vers `/test/login.php` ; les exports et les opérations
administratives exigent une session authentifiée.

La nouvelle fonctionnalité est versionnée, **mais elle ne peut pas annuler à elle seule
la protection cPanel** existante : `.htaccess` et `.htpasswd` sont volontairement
exclus de la synchronisation. Une fois les fichiers déployés et leur présence
vérifiée, aller dans cPanel « Confidentialité des répertoires » et désactiver la
protection Basic Auth du dossier `/test/` uniquement. Si des directives
`AuthType`/`Require valid-user` résiduelles subsistent, les retirer avec soin
sans toucher aux autres directives de sécurité. Vérifier la non-exposition du
sous-répertoire `/test/files/` et de ses anciens contenus.

Avant la bascule, vérifier que les codes émis depuis `contact@lepotager.org`
arrivent bien dans la boîte administrateur. Lors du premier accès, demander un code
d'activation et choisir le mot de passe. Vérifier avec une fenêtre privée que
`/test/admin-testers.php` et `?export=play` sont inaccessibles sans session.
Ne jamais enregistrer de mot de passe, code, JSON d'authentification ou candidat
dans Git : les fichiers `admin-auth.json` et `admin-auth-rates.json` vivent
sous `~/.vevak-private/` avec droits restrictifs.

Les précontrôles de déploiement exécutent `ops/test-admin-auth.php` sur des
données temporaires ; ils ne vérifient pas la distribution réelle des e-mails.
