@@ .. @@
                     <a class="nav-link" href="reports.php">
                         <i class="bi bi-graph-up"></i> Reports
                     </a>
                 </li>
+                <li class="nav-item">
+                    <a class="nav-link" href="analytics.php">
+                        <i class="bi bi-bar-chart"></i> Analytics
+                    </a>
+                </li>
+                <li class="nav-item">
+                    <a class="nav-link" href="documents.php">
+                        <i class="bi bi-files"></i> Documents
+                    </a>
+                </li>
+                <?php if ($auth->hasRole(['admin', 'manager'])): ?>
+                <li class="nav-item">
+                    <a class="nav-link" href="signature-templates.php">
+                        <i class="bi bi-file-earmark-text"></i> Signature Templates
+                    </a>
+                </li>
+                <?php endif; ?>
             </ul>
         </div>
     </nav>