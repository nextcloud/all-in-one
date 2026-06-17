#!/bin/bash
# =============================================================================
# cheats.sh — CLI Cheatsheet for Raspberry Pi & Linux
# Usage: cheats.sh [command]   |   cheats.sh list
# =============================================================================

# ── Colours ──────────────────────────────────────────────────────────────────
RESET='\e[0m';   BOLD='\e[1m'
RED='\e[91m';    YELLOW='\e[93m';  GREEN='\e[92m'
CYAN='\e[96m';   MAGENTA='\e[95m'; WHITE='\e[97m'
# shellcheck disable=SC2034
BLUE='\e[94m'
DIM='\e[2m'

# ── Helpers ───────────────────────────────────────────────────────────────────
header() {
  echo -e "\n${CYAN}${BOLD}╔══════════════════════════════════════════════════╗${RESET}"
  printf "${CYAN}${BOLD}║  %-48s║${RESET}\n" "  $1"
  echo -e "${CYAN}${BOLD}╚══════════════════════════════════════════════════╝${RESET}"
}

sub() {   # sub "title" "description"
  echo -e "\n  ${YELLOW}${BOLD}▸ $1${RESET}  ${DIM}$2${RESET}"
}

cmd() {   # cmd "command string"
  echo -e "    ${GREEN}$1${RESET}"
}

note() {  # note "text"
  echo -e "    ${DIM}# $1${RESET}"
}

tip() {
  echo -e "\n  ${MAGENTA}${BOLD}💡 Tip:${RESET} ${MAGENTA}$1${RESET}"
}

divider() {
  echo -e "  ${DIM}──────────────────────────────────────────────────${RESET}"
}

not_found() {
  echo -e "\n  ${RED}${BOLD}✗  Unknown command:${RESET} ${RED}'$1'${RESET}"
  echo -e "  Run ${CYAN}cheats.sh list${RESET} to see all available topics.\n"
  # Fuzzy suggest
  local matches
  matches=$(echo "${!CHEAT_MAP[@]}" | tr ' ' '\n' | grep -i "$1" | head -5 | tr '\n' '  ')
  [ -n "$matches" ] && echo -e "  ${YELLOW}Did you mean:${RESET} $matches\n"
  exit 1
}

# ── Cheat functions ───────────────────────────────────────────────────────────

cheat_ls() {
  header "ls — list directory contents"
  sub "Basic listing with details" "long format, human-readable sizes"
    cmd "ls -lh"
  divider
  sub "Show all files including hidden" "dotfiles visible, sorted by time"
    cmd "ls -lhAt"
  divider
  sub "List only directories" "using glob or grep"
    cmd "ls -d */"
    note "or:  ls -lh | grep '^d'"
  tip "Add --color=auto to your .bashrc alias for ls."
}

cheat_cd() {
  header "cd — change directory"
  sub "Go to home directory"
    cmd "cd ~"
    cmd "cd"
  divider
  sub "Go up one / two levels"
    cmd "cd .."
    cmd "cd ../.."
  divider
  sub "Jump back to previous directory"
    cmd "cd -"
  tip "Use 'pushd / popd' to manage a directory stack."
}

cheat_cp() {
  header "cp — copy files and directories"
  sub "Copy a file"
    cmd "cp file.txt /destination/"
  divider
  sub "Copy directory recursively" "preserving permissions & timestamps"
    cmd "cp -rp /src/dir /dest/dir"
  divider
  sub "Copy with progress (verbose)"
    cmd "cp -v largefile.img /mnt/usb/"
  tip "rsync is better for large or repeated copies — try 'cheats.sh rsync'."
}

cheat_mv() {
  header "mv — move or rename files"
  sub "Rename a file"
    cmd "mv oldname.txt newname.txt"
  divider
  sub "Move file to a directory"
    cmd "mv report.pdf ~/Documents/"
  divider
  sub "Move multiple files" "using glob"
    cmd "mv *.log /var/archive/"
  tip "mv -i will prompt before overwriting existing files."
}

cheat_rm() {
  header "rm — remove files and directories"
  sub "Remove a file"
    cmd "rm file.txt"
  divider
  sub "Remove directory recursively" "⚠ no recycle bin — permanent"
    cmd "rm -rf /path/to/dir"
  divider
  sub "Interactive delete" "prompts for each file"
    cmd "rm -i *.tmp"
  tip "Use 'trash-cli' (apt install trash-cli) for a safer delete."
}

cheat_find() {
  header "find — search for files"
  sub "Find by filename (case-insensitive)"
    cmd "find /home -iname '*.conf'"
  divider
  sub "Find files modified in last 7 days"
    cmd "find /var/log -mtime -7 -type f"
  divider
  sub "Find and delete files older than 30 days"
    cmd "find /tmp -mtime +30 -type f -delete"
  tip "Combine with -exec to act on results:  find . -name '*.py' -exec wc -l {} +"
}

cheat_grep() {
  header "grep — search text patterns"
  sub "Search a file for a pattern"
    cmd "grep 'error' /var/log/syslog"
  divider
  sub "Case-insensitive, recursive search"
    cmd "grep -ri 'password' /etc/"
  divider
  sub "Show line numbers and 2 lines of context"
    cmd "grep -n -C 2 'failed' /var/log/auth.log"
  tip "Use 'grep -v' to EXCLUDE lines matching a pattern."
}

cheat_sed() {
  header "sed — stream editor"
  sub "Replace first occurrence per line"
    cmd "sed 's/foo/bar/' file.txt"
  divider
  sub "Replace all occurrences, edit file in-place"
    cmd "sed -i 's/oldhost/newhost/g' /etc/hosts"
  divider
  sub "Delete lines matching a pattern"
    cmd "sed '/^#/d' config.txt"
    note "Removes all comment lines starting with #"
  tip "Always test without -i first to preview changes."
}

cheat_awk() {
  header "awk — pattern scanning and processing"
  sub "Print specific column of output"
    cmd "df -h | awk '{print \$1, \$5}'"
    note "Prints filesystem and use% from df"
  divider
  sub "Sum a numeric column"
    cmd "awk '{sum += \$3} END {print sum}' data.txt"
  divider
  sub "Filter rows where column value > threshold"
    cmd "awk '\$3 > 90 {print \$1, \$3}' data.txt"
  tip "awk -F',' for CSV files (sets field separator to comma)."
}

cheat_tar() {
  header "tar — archive files"
  sub "Create a compressed archive"
    cmd "tar -czvf archive.tar.gz /path/to/dir"
  divider
  sub "Extract an archive"
    cmd "tar -xzvf archive.tar.gz"
    cmd "tar -xzvf archive.tar.gz -C /target/dir"
  divider
  sub "List contents without extracting"
    cmd "tar -tzvf archive.tar.gz"
  tip "Use .tar.bz2 for better compression; .tar.xz for best compression."
}

cheat_chmod() {
  header "chmod — change file permissions"
  sub "Make a script executable"
    cmd "chmod +x script.sh"
  divider
  sub "Set explicit octal permissions"
    cmd "chmod 644 config.txt"
    note "644 = owner rw, group r, others r"
    cmd "chmod 755 /var/www/html"
    note "755 = owner rwx, group rx, others rx"
  divider
  sub "Recursive change on directory"
    cmd "chmod -R 750 /opt/myapp"
  tip "Use 'stat -c %a filename' to see current octal permissions."
}

cheat_chown() {
  header "chown — change file owner"
  sub "Change owner and group"
    cmd "chown pi:pi /home/pi/project"
  divider
  sub "Recursive ownership change"
    cmd "chown -R www-data:www-data /var/www/html"
  divider
  sub "Change only group"
    cmd "chown :gpio /dev/gpiomem"
  tip "Combine chown + chmod to lock down sensitive config files."
}

cheat_ssh() {
  header "ssh — secure shell"
  sub "Connect to a remote host"
    cmd "ssh pi@192.168.1.100"
    cmd "ssh -p 2222 user@myserver.com"
  divider
  sub "Run a single command remotely"
    cmd "ssh pi@192.168.1.100 'uptime && df -h'"
  divider
  sub "Copy SSH key for passwordless login"
    cmd "ssh-keygen -t ed25519"
    cmd "ssh-copy-id pi@192.168.1.100"
  tip "Use ~/.ssh/config to define Host aliases and save typing."
}

cheat_scp() {
  header "scp — secure copy over SSH"
  sub "Copy local file to remote"
    cmd "scp file.txt pi@192.168.1.100:/home/pi/"
  divider
  sub "Copy remote file to local"
    cmd "scp pi@192.168.1.100:/home/pi/log.txt ."
  divider
  sub "Copy entire directory recursively"
    cmd "scp -r ./myproject pi@192.168.1.100:/opt/"
  tip "rsync over SSH is faster for large or repeated transfers."
}

cheat_rsync() {
  header "rsync — fast, incremental file transfer"
  sub "Sync local directory to remote"
    cmd "rsync -avz /local/dir/ pi@192.168.1.100:/remote/dir/"
  divider
  sub "Backup with delete (mirror mode)"
    cmd "rsync -avz --delete /src/ /dst/"
  divider
  sub "Dry run — preview without changes"
    cmd "rsync -avzn /local/ pi@192.168.1.100:/remote/"
  tip "Add --progress for per-file transfer feedback on large syncs."
}

cheat_curl() {
  header "curl — transfer data via URL"
  sub "GET a URL and print response"
    cmd "curl https://example.com"
  divider
  sub "Download a file"
    cmd "curl -O https://example.com/file.zip"
    cmd "curl -o myfile.zip https://example.com/file.zip"
  divider
  sub "POST JSON to an API"
    cmd "curl -X POST https://api.example.com/data \\"
    cmd "     -H 'Content-Type: application/json' \\"
    cmd "     -d '{\"key\":\"value\"}'"
  tip "Use -s for silent mode (no progress bar) in scripts."
}

cheat_wget() {
  header "wget — non-interactive download"
  sub "Download a file to current directory"
    cmd "wget https://example.com/file.tar.gz"
  divider
  sub "Download quietly to specific name"
    cmd "wget -q -O output.zip https://example.com/file.zip"
  divider
  sub "Mirror a website recursively"
    cmd "wget -m -p --no-parent https://example.com/docs/"
  tip "wget -c resumes a previously interrupted download."
}

cheat_apt() {
  header "apt — Debian/Raspberry Pi package manager"
  sub "Update package lists & upgrade all"
    cmd "sudo apt update && sudo apt upgrade -y"
  divider
  sub "Install / remove a package"
    cmd "sudo apt install htop"
    cmd "sudo apt remove --purge htop"
  divider
  sub "Search for a package"
    cmd "apt search 'web server'"
    cmd "apt show nginx"
  tip "Use 'apt list --installed' to see all installed packages."
}

cheat_systemctl() {
  header "systemctl — manage systemd services"
  sub "Start / stop / restart a service"
    cmd "sudo systemctl start nginx"
    cmd "sudo systemctl stop nginx"
    cmd "sudo systemctl restart nginx"
  divider
  sub "Enable / disable at boot"
    cmd "sudo systemctl enable nginx"
    cmd "sudo systemctl disable nginx"
  divider
  sub "Check service status & recent logs"
    cmd "systemctl status nginx"
    cmd "journalctl -u nginx -n 50 --no-pager"
  tip "Use 'systemctl list-units --type=service' to see all running services."
}

cheat_journalctl() {
  header "journalctl — query systemd journal logs"
  sub "Tail live log output"
    cmd "journalctl -f"
  divider
  sub "Logs for a specific service"
    cmd "journalctl -u ssh -n 100"
  divider
  sub "Logs since boot / last hour"
    cmd "journalctl -b"
    cmd "journalctl --since '1 hour ago'"
  tip "Add -p err to filter by priority (emerg/alert/crit/err/warning/notice/info/debug)."
}

cheat_ps() {
  header "ps — process status"
  sub "Show all running processes"
    cmd "ps aux"
  divider
  sub "Find a specific process"
    cmd "ps aux | grep nginx"
  divider
  sub "Tree view of processes"
    cmd "ps auxf"
    note "or: pstree -p"
  tip "Use 'htop' for an interactive real-time view."
}

cheat_kill() {
  header "kill / killall — terminate processes"
  sub "Kill by PID"
    cmd "kill 1234"
    cmd "kill -9 1234"
    note "-9 is SIGKILL (force kill, cannot be caught)"
  divider
  sub "Kill by name"
    cmd "killall nginx"
    cmd "pkill -f 'python.*myscript'"
  divider
  sub "List available signals"
    cmd "kill -l"
  tip "Try SIGTERM (default) first; use SIGKILL only if process won't stop."
}

cheat_top() {
  header "top / htop — process monitor"
  sub "Launch top (built-in)"
    cmd "top"
    note "Press M=sort by memory  P=sort by CPU  q=quit"
  divider
  sub "Launch htop (enhanced UI)"
    cmd "htop"
    note "Install with: sudo apt install htop"
  divider
  sub "One-shot top snapshot (no interactive mode)"
    cmd "top -bn1 | head -20"
  tip "In htop: F6=sort  F4=filter  F9=kill  F2=setup"
}

cheat_df() {
  header "df — disk free space"
  sub "Show all mounts, human-readable"
    cmd "df -h"
  divider
  sub "Show only real filesystems (no tmpfs)"
    cmd "df -hT -x tmpfs -x devtmpfs"
  divider
  sub "Check space on a specific path"
    cmd "df -h /var"
  tip "Combine with 'watch -n 5 df -h' to monitor in real time."
}

cheat_du() {
  header "du — disk usage"
  sub "Summarise directory size"
    cmd "du -sh /var/log"
  divider
  sub "Top 10 largest items in a directory"
    cmd "du -h /home | sort -rh | head -10"
  divider
  sub "Max depth breakdown"
    cmd "du -h --max-depth=2 /opt"
  tip "ncdu (apt install ncdu) gives an interactive disk usage explorer."
}

cheat_free() {
  header "free — memory usage"
  sub "Human-readable memory overview"
    cmd "free -h"
  divider
  sub "Update every 2 seconds"
    cmd "free -h -s 2"
  divider
  sub "Show memory in megabytes"
    cmd "free -m"
  tip "On Pi, 'used' includes disk cache; 'available' is the real free figure."
}

cheat_ip() {
  header "ip — network configuration"
  sub "Show all interfaces and IPs"
    cmd "ip addr show"
    cmd "ip a"
  divider
  sub "Show routing table"
    cmd "ip route show"
  divider
  sub "Bring interface up/down"
    cmd "sudo ip link set eth0 up"
    cmd "sudo ip link set eth0 down"
  tip "Use 'nmcli' or 'raspi-config' to configure Wi-Fi on Raspberry Pi."
}

cheat_ping() {
  header "ping — test network connectivity"
  sub "Ping a host (stop with Ctrl+C)"
    cmd "ping 8.8.8.8"
    cmd "ping google.com"
  divider
  sub "Send only 4 packets"
    cmd "ping -c 4 192.168.1.1"
  divider
  sub "Flood ping (root, useful for latency test)"
    cmd "sudo ping -f -c 1000 192.168.1.1"
  tip "Use 'mtr' (apt install mtr) for combined ping+traceroute."
}

cheat_netstat() {
  header "netstat / ss — network connections"
  sub "Show listening TCP ports"
    cmd "ss -tlnp"
    note "t=TCP  l=listening  n=numeric  p=process"
  divider
  sub "All active connections"
    cmd "ss -tunap"
  divider
  sub "Check if a specific port is in use"
    cmd "ss -tlnp | grep :80"
    cmd "lsof -i :80"
  tip "'ss' is the modern replacement for 'netstat'."
}

cheat_ufw() {
  header "ufw — uncomplicated firewall"
  sub "Enable firewall & check status"
    cmd "sudo ufw enable"
    cmd "sudo ufw status verbose"
  divider
  sub "Allow / deny a port"
    cmd "sudo ufw allow 22/tcp"
    cmd "sudo ufw deny 3306"
  divider
  sub "Allow from a specific IP"
    cmd "sudo ufw allow from 192.168.1.0/24 to any port 80"
  tip "Always allow SSH (port 22) BEFORE enabling ufw or you'll lock yourself out."
}

cheat_cron() {
  header "cron / crontab — scheduled tasks"
  sub "Edit current user's crontab"
    cmd "crontab -e"
  divider
  sub "List current crontab"
    cmd "crontab -l"
  divider
  sub "Example crontab entries"
    cmd "*/5 * * * * /home/pi/check.sh"
    note "Every 5 minutes"
    cmd "0 3 * * * /usr/bin/rsync -a /home /backup"
    note "Every day at 3:00 AM"
    cmd "@reboot /home/pi/start_server.sh"
    note "Run once at system boot"
  tip "Use 'crontab.guru' to build and verify cron expressions."
}

cheat_git() {
  header "git — version control"
  sub "Clone a repo & check status"
    cmd "git clone https://github.com/user/repo.git"
    cmd "git status"
  divider
  sub "Stage, commit, push"
    cmd "git add -A"
    cmd "git commit -m 'Your message here'"
    cmd "git push origin main"
  divider
  sub "Branch workflow"
    cmd "git checkout -b feature/my-feature"
    cmd "git merge feature/my-feature"
    cmd "git branch -d feature/my-feature"
  tip "git log --oneline --graph gives a compact visual branch history."
}

cheat_docker() {
  header "docker — container management"
  sub "Pull image & run container"
    cmd "docker pull nginx:latest"
    cmd "docker run -d -p 8080:80 --name mynginx nginx"
  divider
  sub "List & manage containers"
    cmd "docker ps -a"
    cmd "docker stop mynginx && docker rm mynginx"
  divider
  sub "Shell into a running container"
    cmd "docker exec -it mynginx bash"
  tip "Use 'docker compose up -d' to manage multi-container apps."
}

cheat_vim() {
  header "vim — terminal text editor"
  sub "Open / create a file"
    cmd "vim /etc/hosts"
  divider
  sub "Essential mode commands"
    note "i       = enter insert mode"
    note "Esc     = return to normal mode"
    note ":w      = save   |  :q = quit  |  :wq = save+quit"
    note ":q!     = quit without saving"
  divider
  sub "Useful normal-mode shortcuts"
    note "dd = delete line   yy = copy line   p = paste"
    note "/pattern  = search   n = next match"
    note ":%s/old/new/g = global find & replace"
  tip "Run 'vimtutor' for a built-in 30-min interactive tutorial."
}

cheat_nano() {
  header "nano — beginner-friendly text editor"
  sub "Open a file"
    cmd "nano /etc/rc.local"
    cmd "sudo nano /etc/dhcpcd.conf"
  divider
  sub "Key shortcuts (shown in footer)"
    note "Ctrl+O = save (Write Out)   Ctrl+X = exit"
    note "Ctrl+W = search             Ctrl+K = cut line"
    note "Ctrl+U = paste              Ctrl+G = help"
  divider
  sub "Open at a specific line number"
    cmd "nano +42 /etc/nginx/nginx.conf"
  tip "Nano shows all shortcuts at the bottom — ^ means Ctrl."
}

cheat_screen() {
  header "screen — terminal multiplexer"
  sub "Start / list sessions"
    cmd "screen -S mysession"
    cmd "screen -ls"
  divider
  sub "Detach and re-attach"
    note "Ctrl+A then D  = detach"
    cmd "screen -r mysession"
  divider
  sub "Split windows inside screen"
    note "Ctrl+A then |  = vertical split"
    note "Ctrl+A then S  = horizontal split"
    note "Ctrl+A then Tab = switch pane"
  tip "Try 'tmux' for a more modern experience — 'cheats.sh tmux'."
}

cheat_tmux() {
  header "tmux — terminal multiplexer (modern)"
  sub "Start / attach sessions"
    cmd "tmux new -s main"
    cmd "tmux attach -t main"
    cmd "tmux ls"
  divider
  sub "Essential key bindings (prefix = Ctrl+B)"
    note "prefix %   = vertical split"
    note "prefix \"   = horizontal split"
    note "prefix d   = detach session"
    note "prefix arrow = navigate panes"
  divider
  sub "Kill a session"
    cmd "tmux kill-session -t main"
  tip "Add 'set -g mouse on' to ~/.tmux.conf to enable mouse support."
}

cheat_gpio() {
  header "gpio — Raspberry Pi GPIO (raspi-gpio / gpiod)"
  sub "Read all GPIO pin states"
    cmd "raspi-gpio get"
  divider
  sub "Set a pin high/low (BCM numbering)"
    cmd "raspi-gpio set 18 op dh"
    note "op=output  dh=drive high  |  dl=drive low"
  divider
  sub "gpiod tools (modern, kernel interface)"
    cmd "gpiodetect"
    note "List GPIO chips"
    cmd "gpioinfo gpiochip0"
    note "Show all line names"
    cmd "gpioset gpiochip0 18=1"
    note "Set BCM18 high"
  tip "Install gpiod: sudo apt install gpiod  |  Python: import RPi.GPIO as GPIO"
}

cheat_vcgencmd() {
  header "vcgencmd — Raspberry Pi firmware tools"
  sub "Check CPU temperature"
    cmd "vcgencmd measure_temp"
  divider
  sub "Check throttle / voltage status"
    cmd "vcgencmd get_throttled"
    note "0x0 = all clear  |  see 'cheats.sh rpi' for bit meanings"
  divider
  sub "Check clocks and memory split"
    cmd "vcgencmd measure_clock arm"
    cmd "vcgencmd get_mem arm"
    cmd "vcgencmd get_mem gpu"
  tip "vcgencmd commands only work on Raspberry Pi hardware."
}

cheat_rpi() {
  header "rpi — Raspberry Pi quick reference"
  sub "System info & config"
    cmd "cat /proc/device-tree/model"
    note "Show Pi model"
    cmd "vcgencmd measure_temp && vcgencmd get_throttled"
    cmd "sudo raspi-config"
    note "Interactive configuration tool"
  divider
  sub "Throttle flag meanings (get_throttled)"
    note "Bit 0  (0x1)     Under-voltage detected"
    note "Bit 1  (0x2)     ARM frequency capped"
    note "Bit 2  (0x4)     Currently throttled"
    note "Bit 16 (0x10000) Under-voltage occurred (history)"
    note "Bit 18 (0x40000) Throttling occurred (history)"
  divider
  sub "SD card / storage health"
    cmd "sudo hdparm -tT /dev/mmcblk0"
    cmd "df -h && free -h"
  tip "Run 'sudo rpi-healthbench.sh' for a full diagnostic benchmark."
}

cheat_mysql() {
  header "mysql — MySQL / MariaDB CLI"
  sub "Connect to database"
    cmd "mysql -u root -p"
    cmd "mysql -u user -p mydb"
  divider
  sub "Common SQL in CLI"
    cmd "SHOW DATABASES;"
    cmd "USE mydb; SHOW TABLES;"
    cmd "SELECT * FROM users LIMIT 10;"
  divider
  sub "Dump and restore"
    cmd "mysqldump -u root -p mydb > mydb_backup.sql"
    cmd "mysql -u root -p mydb < mydb_backup.sql"
  tip "Use 'EXPLAIN SELECT ...' to diagnose slow queries."
}

cheat_php() {
  header "php — PHP CLI tools"
  sub "Run a PHP script / built-in server"
    cmd "php script.php"
    cmd "php -S 0.0.0.0:8080 -t /var/www/html"
  divider
  sub "Check syntax & version"
    cmd "php -l script.php"
    cmd "php -v"
  divider
  sub "One-liners"
    cmd "php -r 'echo json_encode([\"key\"=>\"val\"]);'"
    cmd "php -r 'phpinfo();' | grep -i 'memory_limit\|upload_max'"
  tip "Install Composer for PHP dependency management."
}

cheat_python() {
  header "python3 — Python quick reference"
  sub "Run a script / interactive shell"
    cmd "python3 script.py"
    cmd "python3"
  divider
  sub "Virtual environments (venv)"
    cmd "python3 -m venv venv"
    cmd "source venv/bin/activate"
    cmd "pip install requests"
    cmd "deactivate"
  divider
  sub "Useful one-liners"
    cmd "python3 -m http.server 8080"
    note "Quick local file server"
    cmd "python3 -c 'import json,sys; print(json.dumps(json.load(sys.stdin), indent=2))'"
    note "Pretty-print JSON:  cat data.json | python3 -c ..."
  tip "Use 'pip list --outdated' to see packages needing updates."
}

cheat_nginx() {
  header "nginx — web server management"
  sub "Start / reload / test config"
    cmd "sudo systemctl restart nginx"
    cmd "sudo nginx -t"
    note "Test config before reloading"
    cmd "sudo nginx -s reload"
  divider
  sub "Enable a site (Debian/Pi style)"
    cmd "sudo ln -s /etc/nginx/sites-available/mysite /etc/nginx/sites-enabled/"
    cmd "sudo nginx -t && sudo systemctl reload nginx"
  divider
  sub "Check error / access logs"
    cmd "sudo tail -f /var/log/nginx/error.log"
    cmd "sudo tail -f /var/log/nginx/access.log"
  tip "Use Certbot (Let's Encrypt) for free HTTPS: 'cheats.sh certbot'."
}

cheat_certbot() {
  header "certbot — Let's Encrypt SSL certificates"
  sub "Issue a certificate for nginx"
    cmd "sudo certbot --nginx -d example.com -d www.example.com"
  divider
  sub "Issue cert only (no auto-config)"
    cmd "sudo certbot certonly --standalone -d example.com"
  divider
  sub "Renew certificates"
    cmd "sudo certbot renew --dry-run"
    cmd "sudo certbot renew"
  tip "Auto-renewal is added by certbot via a systemd timer — check with 'systemctl status certbot.timer'."
}

cheat_watch() {
  header "watch — run a command repeatedly"
  sub "Watch disk space every 2 seconds"
    cmd "watch -n 2 df -h"
  divider
  sub "Watch log file tail"
    cmd "watch -n 1 'tail -20 /var/log/syslog'"
  divider
  sub "Highlight differences between updates"
    cmd "watch -d -n 3 'ss -tunap'"
  tip "Press Ctrl+C to exit. Use -t to remove the header bar."
}

cheat_xargs() {
  header "xargs — build and execute command lines"
  sub "Delete files from find results"
    cmd "find /tmp -name '*.tmp' | xargs rm -f"
  divider
  sub "Run command for each line of a file"
    cmd "cat hosts.txt | xargs -I{} ssh pi@{} 'uptime'"
  divider
  sub "Parallel execution"
    cmd "cat urls.txt | xargs -P 4 -I{} wget -q {}"
    note "-P 4 runs 4 processes in parallel"
  tip "Add -p to xargs to prompt before executing each command."
}

cheat_env() {
  header "env / export — environment variables"
  sub "View all environment variables"
    cmd "env"
    cmd "printenv"
  divider
  sub "Set a temporary variable"
    cmd "export MY_VAR=hello"
    cmd "echo \$MY_VAR"
  divider
  sub "Persist variables for a user"
    cmd "echo 'export MY_API_KEY=abc123' >> ~/.bashrc"
    cmd "source ~/.bashrc"
  tip "Use a .env file + 'set -a; source .env; set +a' to load variables into scripts."
}

cheat_history() {
  header "history — command history"
  sub "View recent history"
    cmd "history"
    cmd "history 30"
    note "Show last 30 commands"
  divider
  sub "Search history"
    cmd "history | grep nginx"
    note "or press Ctrl+R and type to search"
  divider
  sub "Run a previous command by number"
    cmd "!42"
    cmd "!!"
    note "!! repeats the last command"
  tip "Add 'HISTTIMEFORMAT=\"%F %T \"' to .bashrc to timestamp history."
}

# ── List all commands ──────────────────────────────────────────────────────────
show_list() {
  echo -e "\n${CYAN}${BOLD}  cheats.sh — Available Topics${RESET}"
  echo -e "${CYAN}$(printf '═%.0s' {1..60})${RESET}"

  local categories=(
    "FILE & DIRECTORY|ls cd cp mv rm find"
    "TEXT PROCESSING|grep sed awk"
    "ARCHIVES|tar"
    "PERMISSIONS|chmod chown"
    "REMOTE & TRANSFER|ssh scp rsync curl wget"
    "PACKAGES|apt"
    "SERVICES & LOGS|systemctl journalctl"
    "PROCESSES|ps kill top"
    "DISK & MEMORY|df du free"
    "NETWORKING|ip ping netstat ufw"
    "SCHEDULING|cron"
    "EDITORS|vim nano"
    "MULTIPLEXERS|screen tmux"
    "RASPBERRY PI|gpio vcgencmd rpi"
    "WEB & DB|nginx certbot mysql php python"
    "DEVELOPMENT|git docker"
    "SHELL UTILITIES|watch xargs env history"
  )

  for entry in "${categories[@]}"; do
    local cat="${entry%%|*}"
    local cmds="${entry##*|}"
    echo -e "\n  ${YELLOW}${BOLD}${cat}${RESET}"
    echo -e "    ${GREEN}$(echo "$cmds" | tr ' ' '\n' | awk '{printf "%-14s", $0}' | sed 's/  */ /g')${RESET}"
  done
  echo -e "\n${CYAN}$(printf '═%.0s' {1..60})${RESET}"
  echo -e "  Usage: ${WHITE}cheats.sh <topic>${RESET}  e.g.  ${GREEN}cheats.sh grep${RESET}\n"
}

# ── Associative map: keyword → function ───────────────────────────────────────
declare -A CHEAT_MAP=(
  [ls]=cheat_ls         [dir]=cheat_ls
  [cd]=cheat_cd
  [cp]=cheat_cp         [copy]=cheat_cp
  [mv]=cheat_mv         [move]=cheat_mv       [rename]=cheat_mv
  [rm]=cheat_rm         [delete]=cheat_rm     [remove]=cheat_rm
  [find]=cheat_find     [search]=cheat_find
  [grep]=cheat_grep
  [sed]=cheat_sed
  [awk]=cheat_awk
  [tar]=cheat_tar       [zip]=cheat_tar       [archive]=cheat_tar
  [chmod]=cheat_chmod   [permissions]=cheat_chmod
  [chown]=cheat_chown   [owner]=cheat_chown
  [ssh]=cheat_ssh
  [scp]=cheat_scp
  [rsync]=cheat_rsync
  [curl]=cheat_curl
  [wget]=cheat_wget
  [apt]=cheat_apt       [apt-get]=cheat_apt   [install]=cheat_apt
  [systemctl]=cheat_systemctl  [service]=cheat_systemctl
  [journalctl]=cheat_journalctl  [logs]=cheat_journalctl
  [ps]=cheat_ps         [processes]=cheat_ps
  [kill]=cheat_kill     [killall]=cheat_kill  [pkill]=cheat_kill
  [top]=cheat_top       [htop]=cheat_top      [monitor]=cheat_top
  [df]=cheat_df         [disk]=cheat_df
  [du]=cheat_du         [usage]=cheat_du
  [free]=cheat_free     [memory]=cheat_free   [ram]=cheat_free
  [ip]=cheat_ip         [ifconfig]=cheat_ip   [network]=cheat_ip
  [ping]=cheat_ping
  [netstat]=cheat_netstat  [ss]=cheat_netstat  [ports]=cheat_netstat
  [ufw]=cheat_ufw       [firewall]=cheat_ufw
  [cron]=cheat_cron     [crontab]=cheat_cron  [schedule]=cheat_cron
  [git]=cheat_git       [github]=cheat_git
  [docker]=cheat_docker [container]=cheat_docker
  [vim]=cheat_vim       [vi]=cheat_vim
  [nano]=cheat_nano
  [screen]=cheat_screen
  [tmux]=cheat_tmux
  [gpio]=cheat_gpio
  [vcgencmd]=cheat_vcgencmd
  [rpi]=cheat_rpi       [pi]=cheat_rpi        [raspberry]=cheat_rpi
  [mysql]=cheat_mysql   [mariadb]=cheat_mysql  [sql]=cheat_mysql
  [php]=cheat_php
  [python]=cheat_python [python3]=cheat_python [pip]=cheat_python
  [nginx]=cheat_nginx   [web]=cheat_nginx
  [certbot]=cheat_certbot  [ssl]=cheat_certbot  [https]=cheat_certbot
  [watch]=cheat_watch
  [xargs]=cheat_xargs
  [env]=cheat_env       [export]=cheat_env    [environment]=cheat_env
  [history]=cheat_history
)

# ── Entry point ───────────────────────────────────────────────────────────────
QUERY=$(echo "${1:-}" | tr '[:upper:]' '[:lower:]')

if [[ -z "$QUERY" || "$QUERY" == "list" || "$QUERY" == "--list" || "$QUERY" == "-l" ]]; then
  show_list
  exit 0
fi

if [[ -n "${CHEAT_MAP[$QUERY]+x}" ]]; then
  ${CHEAT_MAP[$QUERY]}
  echo ""
else
  not_found "$1"
fi
