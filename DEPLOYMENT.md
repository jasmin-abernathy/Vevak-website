# Mettre VeVak en ligne sur o2switch

Cible de production : `https://vevak.lepotager.org`.

Le site est entièrement statique : il n'a besoin ni de PHP, ni de base de données, ni de Node.js en production.

## Chemin recommandé pour la première mise en ligne

Pour la première publication, utiliser **cPanel → Gestionnaire de fichiers**. C'est le chemin le plus simple à vérifier et il évite de dépendre immédiatement d'un accès SSH automatisé.

Le workflow `.github/workflows/deploy-o2switch.yml` reste disponible pour une automatisation ultérieure, mais o2switch protège normalement SSH/SFTP/FTPS par une autorisation d'adresse IP. Les runners GitHub hébergés n'ont pas une IP fixe pratique à autoriser durablement. Ne pas considérer le workflow SSH comme opérationnel tant que ce point n'a pas été résolu proprement.

## 1. Créer `vevak.lepotager.org` dans cPanel

Dans o2switch / cPanel :

1. ouvrir l'outil **Sous-domaines** dans la rubrique Domaines ;
2. saisir `vevak` comme sous-domaine de `lepotager.org` ;
3. laisser ou choisir une racine de documents dédiée ;
4. noter **exactement** cette racine.

Exemple possible :

```text
/home/UTILISATEUR/vevak.lepotager.org
```

Ne pas deviner le chemin : reprendre celui affiché par cPanel.

Documentation o2switch :
https://faq.o2switch.fr/cpanel/domaines/configuration-sous-domaine/

## 2. Vérifier le DNS

Le sous-domaine doit pointer vers l'hébergement o2switch qui contient sa racine de documents.

Si la zone DNS de `lepotager.org` est déjà gérée par ce compte o2switch, la création du sous-domaine peut suffire selon la configuration existante.

Si la zone DNS est gérée ailleurs, créer l'enregistrement nécessaire vers l'hébergement o2switch concerné. L'adresse IP de l'hébergement est visible dans les informations générales de cPanel.

Ne modifier aucun enregistrement du domaine principal si seul `vevak.lepotager.org` doit être ajouté.

## 3. Préparer les fichiers à envoyer

Depuis GitHub, récupérer le contenu du dépôt `jasmin-abernathy/Vevak-website` sur la branche `main`.

À la racine de `vevak.lepotager.org`, le serveur doit finalement contenir :

```text
index.html
en/
  index.html
assets/
  styles.css
  site.js
  favicon.svg
robots.txt
sitemap.xml
```

Les fichiers de développement (`README.md`, `DEPLOYMENT.md`, `.github/`, `LICENSE`) ne sont pas nécessaires au fonctionnement du site et peuvent rester uniquement sur GitHub.

## 4. Envoyer les fichiers avec le Gestionnaire de fichiers

Dans cPanel :

1. ouvrir **Gestionnaire de fichiers** ;
2. aller dans la racine exacte de `vevak.lepotager.org` ;
3. supprimer uniquement une éventuelle page d'attente créée dans ce dossier, après avoir vérifié que vous êtes dans la bonne racine ;
4. envoyer les fichiers/dossiers du site ;
5. vérifier que `index.html` se trouve directement dans la racine, et non dans un sous-dossier du type `Vevak-website-main/`.

Documentation o2switch :
https://faq.o2switch.fr/cpanel/fichiers/gestionnaire-fichiers-web/

## 5. Tester d'abord en HTTP

Ouvrir :

```text
http://vevak.lepotager.org
```

Le but de ce test est uniquement de confirmer que :

- le DNS arrive au bon hébergement ;
- la racine de documents est correcte ;
- `index.html`, `/assets/` et `/en/` sont accessibles.

Ne pas considérer le déploiement comme terminé tant que HTTPS n'est pas activé.

## 6. Activer HTTPS avec Let's Encrypt

Dans cPanel :

1. ouvrir **Let's Encrypt** ;
2. choisir `vevak.lepotager.org` ;
3. générer le certificat classique ;
4. attendre que le certificat soit installé ;
5. vérifier `https://vevak.lepotager.org`.

Le domaine doit déjà pointer vers o2switch pour que la validation HTTP de Let's Encrypt réussisse.

Documentation o2switch :
https://faq.o2switch.fr/cpanel/securite/lets-encrypt-ssl-gratuit/

## 7. Forcer HTTPS

Dans la racine du site, créer ou modifier `.htaccess` et placer en tête :

```apache
RewriteEngine On
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteCond %{HTTPS} !on
RewriteRule ^(.*) https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

Puis vérifier que :

- `http://vevak.lepotager.org` redirige vers HTTPS ;
- `http://vevak.lepotager.org/en/` redirige également ;
- il n'y a pas de boucle de redirection.

Documentation o2switch :
https://faq.o2switch.fr/guides/webmastering/forcer-https/

## 8. Vérifications finales

Tester au minimum :

- `https://vevak.lepotager.org/` ;
- `https://vevak.lepotager.org/en/` ;
- le bouton FR/EN dans les deux sens ;
- les liens GitHub ;
- l'affichage mobile ;
- `https://vevak.lepotager.org/robots.txt` ;
- `https://vevak.lepotager.org/sitemap.xml` ;
- l'absence d'erreur de certificat ;
- la redirection HTTP → HTTPS.

## Mise à jour manuelle ultérieure

Pour une nouvelle version du site :

1. récupérer la branche `main` à jour ;
2. remplacer `index.html`, `en/`, `assets/`, `robots.txt` et `sitemap.xml` dans la racine de production ;
3. ne pas supprimer `.htaccess` ni `.well-known/` ;
4. recharger la page en navigation privée pour vérifier la version publiée.

## Automatisation GitHub Actions — option avancée

Le dépôt contient un workflow SSH/rsync prévu pour déployer depuis GitHub Actions. Les secrets attendus sont :

- `O2SWITCH_HOST` ;
- `O2SWITCH_USER` ;
- `O2SWITCH_PATH` ;
- `O2SWITCH_SSH_KEY` ;
- `O2SWITCH_KNOWN_HOSTS` ;
- `O2SWITCH_PORT` (facultatif, 22 par défaut).

Cependant, o2switch indique que l'accès SSH doit d'abord être autorisé pour l'adresse IP source. Les runners GitHub hébergés utilisent des adresses qui peuvent changer : une automatisation directe par SSH peut donc être peu fiable sans runner auto-hébergé, IP fixe ou autre mécanisme explicitement validé.

Pour cette raison, **ne pas renseigner ces secrets uniquement pour "essayer"** et ne jamais publier une clé privée dans le dépôt.

Le premier déploiement via Gestionnaire de fichiers reste la procédure de référence tant qu'une stratégie d'automatisation stable n'a pas été choisie.
