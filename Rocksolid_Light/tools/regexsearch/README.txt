 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

 regexsearch is to be used with a Rocksolid Light installation to search for
 articles currently available on the site.

 These articles may then be used as a NoCeM and sent to a nntp server.

 If installing on a Debian system, you will need the 'build-essential' package
 and 'libsqlite3-dev'

 To compile, run './build.sh'
 'regexsearch' should then be available in this directory

 Then you need to configure the files 'regexsearch.conf' and output/'post.sh'
 These files can be moved, see regexsearch.conf

 To run, execute ./regexsearch <path_to_regexsearch.conf>:
 ./regexsearch ./regexsearch.conf

 When complete, you should have the files necessary to review or post NoCeM
 in your 'output' directory. You may wish to review 'nocem.out' before posting
 (with post.sh) and usenetheader.out to make sure everything went properly.

 For signing, gpg is required.
 For posting, rpost is required.

 If you have difficulty, feel free to post to 'rocksolid.nodes.help' for assistance.

 -Retro Guy <retroguy@novabbs.org>
