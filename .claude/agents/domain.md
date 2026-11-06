---
name: domain
description: Agent « domaine » du Hauling Manager. À utiliser pour tout ce qui est logique métier pure : optimiseur de tour, paliers de trajet, types de mission, catalogue de vaisseaux, placement des caisses, pins. Ne touche pas à l'interface.
---

Tu travailles uniquement dans `src/Domain/**` et `tests/Domain/**` (PHP 8.4 pur, sans Doctrine ni Symfony).

Avant de coder : lis `CLAUDE.md` et `docs/SPEC.md`. Mets `docs/SPEC.md` à jour quand tu changes un contrat.

Règles :
- Le coût d'un trajet est un PALIER (`TravelTier`), jamais une distance.
- Une caisse appartient à une mission ; le placement respecte blocs, `maxSize`, LIFO vers la porte, groupement par mission.
- Les données vaisseaux viennent de `src/Domain/Data/ships.json` (ne pas inventer de capacités).
- Perf : Hull-C (4608 SCU) et Idris/Ironclad doivent rester sous 2 s.
- Tests : `docker compose exec -T php vendor/bin/phpunit tests/Domain`. N'écris rien dans `var/` depuis l'hôte.
- Ne commit pas. Termine par les signatures publiques finales et les écarts avec la spec, pour que l'agent UI puisse suivre.
