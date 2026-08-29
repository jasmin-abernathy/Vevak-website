# Déploiement automatique du site VeVak

Cible de production : `https://vevak.lepotager.org`.

Le dépôt `jasmin-abernathy/Vevak-website` est la source du site. Une fois la configuration ci-dessous faite une seule fois, toute modification pertinente poussée sur `main` est publiée automatiquement sur o2switch par GitHub Actions.

## Architecture retenue

```text
modification du site
        ↓
GitHub / branche main
        ↓
GitHub Actions
        ↓
IP temporaire du runner autorisée chez o2switch
        ↓
SSH + rsync
        ↓
vevak.lepotager.org
        ↓
retrait de l'IP temporaire
```

Le mécanisme suit la méthode CI/CD documentée par o2switch : un token API cPanel permet d'autoriser dynamiquement l'adresse IP du runner GitHub pour SSH.

L'APK ne fait pas partie de ce déploiement. Elle est publiée automatiquement dans la release GitHub roulante `beta` du dépôt Android :

`https://github.com/jasmin-abernathy/vevak/releases/download/beta/VeVak-foss-test.apk`

## Ce que le workflow publie

Le workflow `.github/workflows/deploy-o2switch.yml` synchronise :

- `index.html` ;
- `assets/` ;
- `en/` ;
- `soutenir/` ;
- `test/` ;
- `robots.txt` ;
- `sitemap.xml`.

Il préserve volontairement les éléments gérés directement par le serveur, notamment :

- `.htaccess` ;
- `.htpasswd` ;
- `.well-known/` ;
- `/api` et donc le backend Stancer ;
- l'ancien dossier serveur `test/files/` tant qu'il n'est pas nettoyé manuellement.

La protection cPanel de `/test/` n'est donc pas écrasée par un déploiement.

---

# Configuration unique

## 1. Relever les trois informations o2switch

Dans cPanel / le mail de bienvenue o2switch, noter :

1. **le serveur cPanel**, sous la forme `quelquechose.o2switch.net` ;
2. **l'identifiant cPanel** ;
3. **la racine exacte du sous-domaine `vevak.lepotager.org`**.

Le chemin doit être un chemin complet situé sous `/home/IDENTIFIANT/`, par exemple :

```text
/home/monuser/vevak.lepotager.org
```

Ne pas deviner ce chemin : reprendre la racine de documents affichée dans cPanel.

## 2. Créer un token API cPanel

Dans cPanel :

1. ouvrir **Sécurité → API Tokens / Manage API Tokens** ;
2. créer un nouveau token ;
3. lui donner un nom explicite, par exemple `github-vevak-deploy` ;
4. copier immédiatement le token généré.

Le token n'est affiché qu'une fois. Ne pas l'ajouter dans un fichier du dépôt.

Il servira uniquement à appeler l'API o2switch `SshWhitelist` afin d'autoriser temporairement l'IP du runner GitHub.

## 3. Créer une clé SSH dédiée au déploiement

Sur ton ordinateur, créer une paire dédiée :

```bash
ssh-keygen -t rsa -b 4096 -f vevak_github_deploy -N ""
```

Cela crée :

```text
vevak_github_deploy       ← clé privée, secrète
vevak_github_deploy.pub   ← clé publique
```

Ne jamais envoyer la clé privée dans le dépôt Git.

### Installer la clé publique chez o2switch

Dans cPanel, ouvrir **Terminal** puis :

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
nano ~/.ssh/authorized_keys
```

Ajouter sur une nouvelle ligne le contenu complet de `vevak_github_deploy.pub`, enregistrer, puis :

```bash
chmod 644 ~/.ssh/authorized_keys
```

Si `authorized_keys` contient déjà d'autres clés, ne pas les supprimer.

## 4. Récupérer l'identité SSH du serveur

Le workflow utilise `StrictHostKeyChecking=yes` afin de ne pas accepter aveuglément n'importe quel serveur.

Depuis une machine autorisée à se connecter en SSH à l'hébergement :

```bash
ssh-keyscan -H -p 22 TON_SERVEUR.o2switch.net
```

Copier les lignes retournées. Elles deviendront le secret `O2SWITCH_KNOWN_HOSTS`.

Avant cette commande, il peut être nécessaire d'autoriser temporairement l'IP de ta machine dans **cPanel → Autorisation SSH**.

## 5. Ajouter les secrets dans GitHub

Dans :

**GitHub → `jasmin-abernathy/Vevak-website` → Settings → Secrets and variables → Actions → New repository secret**

Créer exactement ces secrets :

| Secret | Contenu |
|---|---|
| `O2SWITCH_HOST` | serveur cPanel, ex. `xxxx.o2switch.net` |
| `O2SWITCH_USER` | identifiant cPanel |
| `O2SWITCH_PATH` | racine exacte de `vevak.lepotager.org` |
| `O2SWITCH_SSH_KEY` | contenu complet de la clé privée `vevak_github_deploy` |
| `O2SWITCH_KNOWN_HOSTS` | sortie vérifiée de `ssh-keyscan` |
| `O2SWITCH_CPANEL_API_TOKEN` | token API créé à l'étape 2 |

Il n'y a pas besoin de secret FTP, de mot de passe cPanel ni de token GitHub personnel.

## 6. Premier test volontaire

Une fois les six secrets ajoutés :

1. ouvrir l'onglet **Actions** du dépôt ;
2. choisir **Deploy VeVak website to o2switch** ;
3. choisir **Run workflow** sur `main` ;
4. suivre les étapes.

Le workflow doit successivement :

- valider la configuration ;
- préparer les fichiers statiques ;
- détecter l'IP publique du runner ;
- créer une exception SSH temporaire chez o2switch ;
- configurer la clé SSH ;
- synchroniser le site ;
- vérifier l'accueil et `/soutenir/` ;
- vérifier si `/test/` répond bien comme zone protégée ;
- retirer l'exception SSH temporaire.

## 7. Fonctionnement quotidien

Après ce premier test réussi, aucune action manuelle n'est nécessaire.

Un push sur `main` déclenche le déploiement si le commit modifie au moins un de ces chemins :

```text
index.html
assets/**
en/**
soutenir/**
test/**
robots.txt
sitemap.xml
.github/workflows/deploy-o2switch.yml
```

Un changement uniquement dans un README ou une documentation ne déclenche donc pas inutilement la production.

## 8. Retour arrière

Si une mise à jour du site pose problème :

1. revenir sur GitHub au dernier commit correct ;
2. faire un **Revert** du commit problématique ou pousser un correctif ;
3. le nouveau commit sur `main` redéploiera automatiquement l'état corrigé.

Le serveur n'est plus la source de vérité : la source de vérité est la branche `main` du dépôt.

## 9. Points de sécurité

- ne jamais ajouter de clé privée ou token API dans un fichier Git ;
- le workflow ne supprime jamais toutes les IP de la whitelist o2switch ;
- il ajoute uniquement son IP temporaire et ne la retire que s'il l'a lui-même ajoutée ;
- si les 5 exceptions SSH o2switch sont déjà utilisées, le déploiement échoue sans en supprimer une arbitrairement ;
- les déploiements sont sérialisés afin d'éviter deux synchronisations concurrentes ;
- la clé privée est supprimée du runner à la fin ;
- les fichiers serveur sensibles sont préservés.

## 10. Cas particulier : 2FA cPanel

La documentation o2switch indique que lorsque la 2FA cPanel est activée, les appels API non-session peuvent également demander un code à usage unique.

Ne stocke pas la graine TOTP/QR code de ta 2FA dans GitHub pour contourner ce mécanisme.

Si le workflow échoue sur l'étape `Temporarily whitelist runner on o2switch` alors que le token est correct et que la 2FA est active, utiliser plutôt une stratégie de déploiement tirée depuis l'hébergement (cron + dépôt Git public) ou un runner avec IP stable. Ce cas doit être traité séparément plutôt que d'affaiblir la 2FA.

## Documentation de référence

- o2switch — Autorisation SSH / CI-CD : `https://faq.o2switch.fr/cpanel/outils/exception-parefeu/`
- o2switch — Token API cPanel : `https://faq.o2switch.fr/cpanel/securite/token-api-cpanel/`
- o2switch — Connexion et clés SSH : `https://faq.o2switch.fr/guides/webmastering/connexion-ssh/`

## État actuel

Le workflow est déjà installé dans le dépôt. Tant que les six secrets ci-dessus ne sont pas renseignés, il se met volontairement en veille et ne touche pas au serveur.
