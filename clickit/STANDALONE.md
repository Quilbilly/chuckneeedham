# ClickIt should be its own Cursor project

ClickIt was accidentally started inside the `chuckneeedham` website repo. It should live in a **separate GitHub repo** and a **separate Cursor project**.

## Recommended fix

### 1. Create a new GitHub repo
Create an empty repo, e.g. `clickit` (private is fine).

### 2. Publish just the ClickIt folder
From a machine with this code:

```bash
# copy the app out of the website repo
cp -R /path/to/chuckneeedham/clickit ~/Projects/clickit
cd ~/Projects/clickit

git init
git add .
git commit -m "Initial ClickIt photo booth app"
git branch -M main
git remote add origin https://github.com/<you>/clickit.git
git push -u origin main
```

### 3. Open it in Cursor as its own project
**File → Open Folder** → select `~/Projects/clickit`  
(or clone the new repo and open that).

Do **not** open the whole chuckneedham site when working on ClickIt.

### 4. Clean up the website repo
On the chuckneedham PR/branch, remove the `clickit/` folder (or close that PR) so the personal site stays separate.

## Sony SDK

After the new project exists, put the SDKs in:

```
vendor/sony-camera-remote-sdk/windows/
vendor/sony-camera-remote-sdk/macos/
vendor/sony-camera-remote-sdk/linux/
```

See `vendor/sony-camera-remote-sdk/README.md`.
