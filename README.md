Extropy
===================
__Minecraft: Pocket Edition server software__

## NOTICE: 0.16 IS NOT SUPPORTED YET.

Introduction
-------------

Extropy is an implementation of the Minecraft: Pocket Edition protocol that allows clients to connect and play
together. This software is based on PocketMine-Soft-235 and aims to back port the new Minecraft: PE protocol
to an older PocketMine version for better stability and performance, while implementing as many features from the new
protocol as possible. Extropy is currently compatible with all clients running Minecraft: PE 0.15.0 alpha and above.

Things you might want to change before building:
  - Saving the server.log is disabled because it takes a lot of time to write to disk
  - Saving player inventory and location is disabled by default as the software is optimised for mini-games servers
  - Chunk generation is disabled by default as Extropy is optimised for mini-game servers
  - Spawn protection is disabled as it takes time to check positions every time a player performs a simple action

Known bugs:
   - Performance isn't as good as 1.4, some profiling needs to be done

To build, run the server with DevTools installed then run /makeserver. It'll drop a phar file in it's plugin directory.

The content of this repo is licensed under the GNU Lesser General Public License v2.1. A full copy of the license is
available [here](LICENSE).