# Comment réparer les tables et colonnes manquantes

Utilisez `sql/init.sql` pour restaurer le schéma manquant avant d'exécuter les migrations.

1. Sauvegardez la base de données :

   ```sh
   ept backup
   ```

2. Prévisualisez les réparations :

   ```sh
   php bin/heal-schema.php
   ```

   La commande vérifie chaque table et chaque colonne définies dans `sql/init.sql`.
   Elle affiche le SQL proposé sans l'exécuter.
   Les colonnes existantes conservent leurs types, leurs valeurs par défaut et leurs valeurs.
   Les tables et colonnes supplémentaires restent inchangées.

3. Appliquez les réparations et reprenez les migrations :

   ```sh
   ept migrate
   ```

   `ept migrate` et `ept post-update` exécutent tous deux la réparation avant les migrations.
   Pour appliquer uniquement les réparations, exécutez `ept heal-schema` ou `php bin/heal-schema.php --apply`.

   Les nouvelles tables reçoivent les index, les clés étrangères et les définitions `AUTO_INCREMENT` finales du dump.
   Les nouvelles tables sont vides. La réparation n'importe pas de lignes initiales et ne récupère pas les données perdues.
   Si nécessaire, restaurez les lignes perdues à partir d'une sauvegarde.

   Lorsqu'une ancienne table ou colonne existe encore, la réparation reporte la destination de sa migration.
   Exécutez les migrations pour déplacer ces données avant de vérifier de nouveau.
   La commande affiche chaque destination reportée.

   Les index existants restent inchangés, sauf si une colonne `AUTO_INCREMENT` manquante doit être ajoutée avec son index.
   Les clés en conflit et les instructions de schéma non prises en charge arrêtent la réparation avec une erreur.
   Le DDL déjà exécuté avec succès est conservé après un échec. Résolvez le problème signalé et relancez la commande.
   La réparation ne fait pas avancer `app_version`.

4. Vérifiez la réparation et l'état des migrations :

   ```sh
   php bin/heal-schema.php
   php bin/migrate.php --status
   ```

   Vous devez obtenir `0 repair(s) planned`, aucune destination reportée et la version applicative attendue.
