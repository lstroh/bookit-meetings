# 📘 Vue Development Server Guide

Create this file: **`dashboard/DEV-SERVER-GUIDE.md`**

```markdown
# Vue Development Server Guide
**Bookit Dashboard - Quick Reference**

---

## 🚀 START THE DEV SERVER

```bash
cd wp-content/plugins/bookit-booking-system/dashboard
npm run dev
```

**Expected output:**
```
VITE v5.1.4  ready in 324 ms

➜  Local:   http://localhost:5173/
➜  Network: use --host to expose
➜  press h + enter to show help
```

**What happens:**
- Vite dev server starts on port 5173
- Hot Module Replacement (HMR) enabled
- Changes to Vue files auto-reload in browser
- Console shows compilation status

**Leave this terminal open while developing!**

---

## 🛑 STOP THE DEV SERVER

**Method 1: Keyboard Shortcut**
```bash
Ctrl + C
```
Press once, wait 2 seconds. If it doesn't stop:
```bash
Ctrl + C
Ctrl + C
```
(Press twice rapidly)

**Method 2: Close Terminal**
- Just close the terminal window
- Server stops automatically

**Method 3: Kill by Port (if stuck)**

**macOS/Linux:**
```bash
lsof -ti:5173 | xargs kill -9
```

**Windows (Command Prompt):**
```cmd
netstat -ano | findstr :5173
taskkill /PID <PID_NUMBER> /F
```

**Windows (PowerShell):**
```powershell
Get-Process -Id (Get-NetTCPConnection -LocalPort 5173).OwningProcess | Stop-Process -Force
```

---

## 🔄 RESTART THE DEV SERVER

**Quick restart:**
```bash
# In the terminal where dev server is running:
Ctrl + C          # Stop
npm run dev       # Start again
```

**Why restart?**
- `.env` file changes (if you add one later)
- `vite.config.js` changes
- `package.json` dependency changes
- Server becomes unresponsive

**You DON'T need to restart for:**
- Vue component changes (`.vue` files)
- JavaScript/CSS changes in `src/`
- Changes to `tailwind.config.js` (auto-reloads)

---

## 🔍 CHECK IF SERVER IS RUNNING

**Method 1: Check Terminal**
- Look for the terminal window with Vite output
- Should show "ready in XXX ms"

**Method 2: Test the URL**
```bash
curl http://localhost:5173
```
If running, returns HTML. If not, connection refused.

**Method 3: Browser**
- Go to: http://localhost:5173
- Should see "Vite" or your app
- If "connection refused" → server not running

**Method 4: Check Processes**

**macOS/Linux:**
```bash
lsof -i :5173
```

**Windows:**
```cmd
netstat -ano | findstr :5173
```

---

## 🐛 TROUBLESHOOTING

### Problem: Port 5173 Already in Use

**Error message:**
```
Port 5173 is in use, trying another one...
Error: Could not find an open port
```

**Solution 1: Kill existing process**
```bash
# macOS/Linux
lsof -ti:5173 | xargs kill -9

# Windows
netstat -ano | findstr :5173
taskkill /PID <PID> /F
```

**Solution 2: Use different port**

Edit `dashboard/vite.config.js`:
```js
server: {
  port: 5174,  // Change from 5173
  strictPort: false
}
```

Then:
```bash
npm run dev
```

---

### Problem: "command not found: npm"

**Cause:** Node.js not installed or not in PATH

**Solution:**
```bash
# Check Node.js version
node --version

# Should show: v22.19.0 (or similar)
# If not found, reinstall Node.js
```

**Install Node.js:**
- Download from: https://nodejs.org/
- Or use nvm: https://github.com/nvm-sh/nvm

---

### Problem: Changes Not Showing in Browser

**Symptoms:**
- Edit Vue file
- Save
- Browser doesn't update

**Solutions:**

**1. Check terminal for errors**
```bash
# Look for compilation errors in dev server output
```

**2. Hard refresh browser**
```bash
Windows/Linux: Ctrl + Shift + R
macOS:         Cmd + Shift + R
```

**3. Clear browser cache**
```bash
Chrome DevTools → Network tab → "Disable cache" checkbox
```

**4. Restart dev server**
```bash
Ctrl + C
npm run dev
```

**5. Check file is saved**
```bash
# Cursor might not have auto-saved
File → Save All (Ctrl/Cmd + K, S)
```

---

### Problem: White Screen / Blank Page

**Symptoms:**
- Dashboard loads but shows white screen
- No content visible

**Solutions:**

**1. Check browser console (F12)**
```js
// Look for errors like:
// "Failed to fetch dynamically imported module"
// "Unexpected token '<'"
```

**2. Verify dev server is running**
```bash
# Should see in terminal:
➜  Local:   http://localhost:5173/
```

**3. Check app/index.php is loading correct URLs**

Open browser DevTools → Network tab:
- Should see requests to `localhost:5173`
- If 404 errors → dev server not running
- If CORS errors → wrong URL in PHP

**4. Clear dist/ folder**
```bash
rm -rf dashboard/dist/
npm run dev
```

---

### Problem: npm ERR! code ENOENT

**Error message:**
```
npm ERR! code ENOENT
npm ERR! syscall open
npm ERR! path /path/to/dashboard/package.json
```

**Cause:** Wrong directory

**Solution:**
```bash
# Make sure you're in the dashboard folder
cd wp-content/plugins/bookit-booking-system/dashboard
pwd  # Should end in /dashboard

# Then run:
npm run dev
```

---

### Problem: Module Not Found

**Error in terminal:**
```
Error: Cannot find module 'vue'
```

**Solution:**
```bash
cd dashboard
rm -rf node_modules package-lock.json
npm install
npm run dev
```

---

## 📊 DEV SERVER STATUS INDICATORS

### ✅ Server Running Correctly
```
VITE v5.1.4  ready in 324 ms

➜  Local:   http://localhost:5173/
➜  Network: use --host to expose
```

### ⚠️ Server Running with Warnings
```
VITE v5.1.4  ready in 324 ms

➜  Local:   http://localhost:5173/

warnings when minifying css:
▲ [WARNING] ...
```
**Action:** Check warnings, usually safe to ignore during dev

### ❌ Server Failed to Start
```
Error: listen EADDRINUSE: address already in use :::5173
```
**Action:** Port in use, kill existing process or change port

### 🔄 Server Restarting
```
hmr update /src/components/Sidebar.vue
```
**Action:** None, this is normal HMR (Hot Module Replacement)

---

## 🎯 QUICK COMMANDS CHEAT SHEET

| Action | Command |
|--------|---------|
| **Start** | `npm run dev` |
| **Stop** | `Ctrl + C` |
| **Restart** | `Ctrl + C` then `npm run dev` |
| **Kill port** | `lsof -ti:5173 \| xargs kill -9` |
| **Check running** | `lsof -i :5173` |
| **Build production** | `npm run build` |
| **Preview build** | `npm run preview` |
| **Install deps** | `npm install` |
| **Clean install** | `rm -rf node_modules && npm install` |

---

## 🔧 ADVANCED: Background Process

**Run dev server in background (macOS/Linux):**

```bash
cd dashboard
npm run dev > dev-server.log 2>&1 &
echo $! > .dev-server.pid
```

**Check status:**
```bash
cat .dev-server.pid  # Shows process ID
ps -p $(cat .dev-server.pid)  # Check if running
```

**Stop background server:**
```bash
kill $(cat .dev-server.pid)
rm .dev-server.pid
```

**View logs:**
```bash
tail -f dev-server.log
```

---

## 📝 DEVELOPMENT WORKFLOW

### Daily Routine

**Morning (start coding):**
```bash
cd wp-content/plugins/bookit-booking-system/dashboard
npm run dev
# Leave terminal open, minimize it
```

**During development:**
- Edit Vue files in Cursor
- Save (Ctrl/Cmd + S)
- Browser auto-reloads
- Check terminal for errors

**Breaks:**
- Leave server running (uses minimal resources)
- Or stop with `Ctrl + C` if leaving computer

**End of day:**
```bash
# Stop dev server
Ctrl + C

# Optional: Build production
npm run build

# Commit changes
git add .
git commit -m "Sprint 3, Task X: Description"
```

---

## 🎓 KEYBOARD SHORTCUTS IN DEV SERVER

While dev server terminal is focused:

| Key | Action |
|-----|--------|
| `h` + Enter | Show help menu |
| `r` + Enter | Restart server |
| `u` + Enter | Show server URL |
| `o` + Enter | Open browser automatically |
| `c` + Enter | Clear console |
| `q` + Enter | Quit server |

---

## 🌐 ACCESSING FROM OTHER DEVICES

**Test dashboard on phone/tablet:**

```bash
# Start with network access
npm run dev -- --host

# Output shows:
➜  Local:   http://localhost:5173/
➜  Network: http://192.168.1.100:5173/
```

**On your phone:**
1. Connect to same WiFi as development machine
2. Visit: `http://192.168.1.100:5173` (use IP from output)
3. Navigate to full path: `/bookit-dashboard/app/`

**Troubleshooting network access:**
- Check firewall allows port 5173
- Ensure both devices on same network
- Use computer's local IP, not localhost

---

## 💡 TIPS & BEST PRACTICES

### ✅ DO:
- Keep dev server running while developing
- Check terminal for compilation errors
- Use browser DevTools (F12) for debugging
- Hard refresh (Ctrl+Shift+R) if changes don't appear
- Restart server after config changes

### ❌ DON'T:
- Run multiple dev servers simultaneously
- Edit files in `dist/` folder (gets overwritten)
- Commit `node_modules/` to Git
- Forget to stop server when done (wastes resources)
- Ignore error messages in terminal

---

## 📚 RELATED FILES

- **Start dev:** `npm run dev` (runs `vite` from package.json)
- **Config:** `dashboard/vite.config.js`
- **Entry point:** `dashboard/src/main.js`
- **PHP wrapper:** `dashboard/app/index.php`
- **Build output:** `dashboard/dist/`

---

## 🆘 STILL HAVING ISSUES?

**Check Node.js version:**
```bash
node --version  # Should be v16+ (you have v22.19.0 ✅)
npm --version   # Should be v8+
```

**Reinstall dependencies:**
```bash
cd dashboard
rm -rf node_modules package-lock.json
npm install
```

**Nuclear option (clean slate):**
```bash
cd dashboard
rm -rf node_modules package-lock.json dist/
npm install
npm run dev
```

**Check Vite documentation:**
https://vitejs.dev/guide/

---

**Last updated:** Sprint 3, Task 1  
**Vite version:** 5.1.4  
**Vue version:** 3.4.0  
**Node.js version:** 22.19.0
```

---

## 🎯 USAGE

Save this file, then you can:

```bash
# Quick reference anytime:
cat dashboard/DEV-SERVER-GUIDE.md

# Or open in Cursor:
code dashboard/DEV-SERVER-GUIDE.md
```

---

## 📝 ALTERNATIVE: One-Page Quick Reference Card

If you want something even shorter, create **`dashboard/DEV-COMMANDS.txt`**:

```
BOOKIT DASHBOARD - DEV SERVER QUICK REFERENCE
==============================================

START:     cd dashboard && npm run dev
STOP:      Ctrl + C
RESTART:   Ctrl + C, then npm run dev
BUILD:     npm run build

KILL PORT: lsof -ti:5173 | xargs kill -9  (macOS/Linux)
           netstat -ano | findstr :5173   (Windows - get PID, then taskkill)

CHECK:     lsof -i :5173                  (macOS/Linux)
           netstat -ano | findstr :5173   (Windows)

CLEAN:     rm -rf node_modules package-lock.json && npm install

URL:       http://localhost:5173/
DASHBOARD: http://plugin-test-1.local/bookit-dashboard/app/

LOGS:      Check terminal where npm run dev is running
ERRORS:    Browser DevTools (F12) → Console tab

DAILY WORKFLOW:
1. cd dashboard
2. npm run dev (leave running)
3. Edit Vue files in Cursor
4. Browser auto-reloads
5. Ctrl + C when done
```

---

**Which one do you prefer?**
- Full guide (DEV-SERVER-GUIDE.md) - comprehensive with troubleshooting
- Quick reference (DEV-COMMANDS.txt) - one-page cheat sheet
- Both? 😊

I can create whichever you'd like! Let me know and I'll add it to your Task 1 commit, or you can add it separately.