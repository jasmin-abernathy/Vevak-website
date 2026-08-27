# Déploiement automatique de VeVak vers o2switch

Objectif : chaque modification publiée sur la branche `main` de `jasmin-abernathy/Vevak-website` met automatiquement à jour `https://vevak.lepotager.org`.

Le workflow est déjà présent dans `.github/workflows/deploy-o2switch.yml`. Tant que les secrets o2switch ne sont pas configurés dans GitHub, il reste volontairement inactif et ne tente aucune connexion.

## 1. Créer `vevak.lepotager.org` dans cPanel

Dans o2switch / cPanel :

1. ouvrir **Domaines** ;
2. créer le sous-domaine `vevak.lepotager.org` ;
3. noter exactement sa **racine du document**.

La racine peut ressembler à `/home/UTILISATEUR/vevak.lepotager.org`, mais il ne faut pas la deviner : recopier la valeur affichée par cPanel.

Si la zone DNS de `lepotager.org` n'est pas gérée par ce cPanel, ajouter le sous-domaine dans la zone DNS utilisée pour `lepotager.org`, en le faisant pointer vers l'adresse de l'hébergement o2switch concerné.

## 2. Identifier l'accès SSH o2switch

Dans cPanel, récupérer :

- le **nom d'hôte SSH** du serveur ;
- le **nom d'utilisateur cPanel/SSH** ;
- le port SSH, généralement `22` si aucune autre valeur n'est indiquée.

Ne pas utiliser comme identifiant une adresse du type `jasmin@nom-du-serveur` : GitHub attend séparément le nom d'utilisateur et le nom d'hôte.

## 3. Créer une clé de déploiement dédiée

Depuis un terminal local :

```bash
ssh-keygen -t ed25519 -C "github-vevak-deploy" -f vevak_deploy
```

Cela crée :

- `vevak_deploy` : clé **privée** ;
- `vevak_deploy.pub` : clé **publique**.

Ajouter/autoriser le contenu de `vevak_deploy.pub` dans l'accès SSH du compte o2switch.

Ne jamais publier `vevak_deploy` dans le dépôt.

## 4. Enregistrer l'empreinte du serveur

Avec le vrai nom d'hôte SSH :

```bash
ssh-keyscan -p 22 NOM_HOTE_SSH
```

Si o2switch utilise un autre port, remplacer `22`.

Conserver la ou les lignes retournées. Elles seront ajoutées à GitHub dans le secret `O2SWITCH_KNOWN_HOSTS` afin que le déploiement refuse un serveur SSH inattendu.

## 5. Ajouter les secrets dans GitHub

Dans :

**Vevak-website → Settings → Secrets and variables → Actions → New repository secret**

Créer :

| Secret | Valeur |
|---|---|
| `O2SWITCH_HOST` | nom d'hôte SSH o2switch |
| `O2SWITCH_USER` | utilisateur SSH/cPanel |
| `O2SWITCH_PATH` | racine exacte de `vevak.lepotager.org` |
| `O2SWITCH_SSH_KEY` | contenu complet de la clé privée `vevak_deploy` |
| `O2SWITCH_KNOWN_HOSTS` | sortie vérifiée de `ssh-keyscan` |
| `O2SWITCH_PORT` | facultatif ; laisser absent pour utiliser `22` |

Le workflow refuse explicitement une racine vide ou `/`.

## 6. Premier déploiement

Après avoir ajouté les secrets :

1. ouvrir l'onglet **Actions** du dépôt ;
2. choisir **Deploy VeVak website to o2switch** ;
3. cliquer sur **Run workflow** ;
4. vérifier que le job se termine en vert ;
5. ouvrir `https://vevak.lepotager.org`.

## 7. Ensuite : fonctionnement automatique

Une modification de l'un de ces éléments sur `main` déclenche automatiquement le déploiement :

- `index.html` ;
- `en/**` ;
- `assets/**` ;
- `robots.txt` ;
- `sitemap.xml` ;
- le workflow lui-même.

Les modifications purement documentaires du README ne republient donc pas inutilement le site.

## Sécurité et comportement du déploiement

Le transfert utilise SSH + `rsync` avec vérification stricte de l'empreinte du serveur.

Le workflow n'utilise pas `--delete` : une mauvaise racine distante ne peut donc pas provoquer une suppression massive des fichiers existants. Les dossiers `.well-known/` et le fichier `.htaccess` ne sont pas envoyés ni écrasés par le workflow.

Une fois la racine vérifiée en production, une stratégie de nettoyage contrôlé pourra être ajoutée si des fichiers obsolètes deviennent réellement un problème.
