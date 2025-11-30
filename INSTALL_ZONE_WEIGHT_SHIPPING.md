# 🚀 How to Install Zone Weight Shipping Extension

## ⚠️ Important: Installation Steps Required!

You **cannot** access the extension directly via URL. You must install it through the OpenCart Extensions system first.

## 📋 Installation Steps

### Step 1: Clear Cache (IMPORTANT!)

```
Go to: System → Maintenance → Refresh
Or delete: C:\xampp\htdocs\storage_elc\cache\*
```

### Step 2: Navigate to Extensions

```
1. Login to Admin Panel: http://elc.local/adminelc/
2. Go to: Extensions → Extensions
3. From dropdown: Select "Shipping"
```

### Step 3: Find Zone Weight Shipping

Look for **"Zone Weight Shipping"** in the list.

If you DON'T see it:
- ✓ Make sure extension folder exists at: `extension/zone_weight_shipping/`
- ✓ Clear cache again
- ✓ Refresh the page

### Step 4: Install the Extension

```
1. Find "Zone Weight Shipping" in the list
2. Click the GREEN "+" (Install) button
3. Wait for success message
```

### Step 5: Access Configuration

```
After installation:
1. You'll see a BLUE pencil icon (Edit)
2. Click it to configure the extension
3. This opens: http://elc.local/adminelc/index.php?route=extension/zone_weight_shipping/shipping/zone_weight&user_token=...
```

## 🎯 Quick Access Path

**The correct flow:**

```
Admin Panel
└── Extensions
    └── Extensions
        └── Filter: Shipping
            └── Zone Weight Shipping
                ├── [+] Install (click first)
                └── [✏] Edit (click after install)
```

## 🔧 If Extension Doesn't Appear

### Check 1: Folder Structure
```
extension/zone_weight_shipping/
├── admin/
│   └── controller/
│       └── shipping/
│           └── zone_weight.php  ← File must exist!
├── catalog/
│   └── model/
│       └── shipping/
│           └── zone_weight.php
└── install.json
```

### Check 2: Clear Cache
```
System → Maintenance → Refresh
Check ALL boxes
Click Refresh
```

### Check 3: Permissions
```
System → Users → User Groups
Edit "Administrator"
Scroll down and add:
- Access: extension/zone_weight_shipping/shipping/zone_weight
- Modify: extension/zone_weight_shipping/shipping/zone_weight
```

### Check 4: File Permissions (Windows)
Files should be readable. Check folder isn't read-only.

## 🎨 What You'll See

### In Extensions List (Before Install):
```
┌─────────────────────────────────────────────────────┐
│ Zone Weight Shipping                                │
│ Status: ● (Not installed)                           │
│ Sort Order: -                                       │
│ Actions: [+] Install                                │
└─────────────────────────────────────────────────────┘
```

### In Extensions List (After Install):
```
┌─────────────────────────────────────────────────────┐
│ Zone Weight Shipping                                │
│ Status: ✓ Enabled / ✗ Disabled                     │
│ Sort Order: 1                                       │
│ Actions: [✏] Edit  [-] Uninstall                   │
└─────────────────────────────────────────────────────┘
```

## ⚙️ Configuration Page Layout

After clicking Edit, you'll see:

```
┌─────────────────────────────────────────────────────┐
│ Zone Weight Shipping                                │
├─────────────────────────────────────────────────────┤
│ Tabs:                                               │
│ [General] [UK Shipping] [US Shipping] [...]        │
├─────────────────────────────────────────────────────┤
│ General Tab:                                        │
│ - Title: Delivery Service                          │
│ - Tax Class: [Select]                              │
│ - Status: ☑ Enabled                                │
│ - Sort Order: 1                                    │
├─────────────────────────────────────────────────────┤
│ Zone Tabs (one per geo zone):                      │
│ - City Rate: 5.00                                  │
│ - Primary Weight Rate: 10.00                       │
│ - Additional Weight Rate: 2.00                     │
│ - Status: ☑ Enabled                                │
└─────────────────────────────────────────────────────┘
```

## 🚫 Common Errors

### Error: "Page Not Found"
**Cause:** Trying to access before installing  
**Solution:** Install through Extensions → Extensions first

### Error: Extension not in list
**Cause:** Cache or folder structure  
**Solution:** Clear cache, check folder structure

### Error: "Permission Denied"
**Cause:** User permissions not set  
**Solution:** Add permissions in User Groups

## ✅ Complete Installation Checklist

- [ ] Extension folder exists: `extension/zone_weight_shipping/`
- [ ] All files are in place (controller, model, view, language)
- [ ] Cache cleared
- [ ] Browser refreshed
- [ ] Navigated to Extensions → Extensions → Shipping
- [ ] Found "Zone Weight Shipping" in list
- [ ] Clicked Install (green +)
- [ ] Saw success message
- [ ] Clicked Edit (blue pencil)
- [ ] Configuration page loaded
- [ ] Configured General settings
- [ ] Configured at least one geo zone
- [ ] Clicked Save
- [ ] Module ready to use!

## 📞 Still Having Issues?

### Debug Steps:

1. **Check if extension folder exists:**
   ```
   C:\xampp\htdocs\elc\extension\zone_weight_shipping\admin\controller\shipping\zone_weight.php
   ```

2. **Check PHP error logs:**
   ```
   C:\xampp\apache\logs\error.log
   C:\xampp\htdocs\elc\system\storage\logs\error.txt
   ```

3. **Browser console:**
   ```
   Press F12
   Check for JavaScript errors
   ```

4. **Test with another extension:**
   - See if other shipping methods show up
   - If not, might be a broader issue

## 🎉 Success Indicators

You'll know it's working when:

✅ Extension appears in Extensions → Shipping list  
✅ Install button works without errors  
✅ Edit button appears after install  
✅ Configuration page loads properly  
✅ Can save settings  
✅ Shipping method shows at checkout (when configured)  

## 🔄 Reinstalling

If you need to reinstall:

1. **Uninstall first:**
   - Click red "-" (Uninstall) button
   - Confirm

2. **Clear cache:**
   - System → Maintenance → Refresh

3. **Install again:**
   - Click green "+" (Install) button

---

**Remember:** You MUST install through the Extensions interface before you can access the configuration page!

**Correct URL (after installation):**
```
http://elc.local/adminelc/index.php?route=extension/zone_weight_shipping/shipping/zone_weight&user_token=YOUR_TOKEN
```

The URL is automatically generated when you click the Edit button.


