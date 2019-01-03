<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmPercent
    Inherits DevExpress.XtraEditors.XtraForm

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        If disposing AndAlso components IsNot Nothing Then
            components.Dispose()
        End If
        MyBase.Dispose(disposing)
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Me.GroupBox1 = New System.Windows.Forms.GroupBox()
        Me.cbPrint = New System.Windows.Forms.ComboBox()
        Me.Label44 = New System.Windows.Forms.Label()
        Me.GroupBox2 = New System.Windows.Forms.GroupBox()
        Me.GroupBox17 = New System.Windows.Forms.GroupBox()
        Me.Label25 = New System.Windows.Forms.Label()
        Me.Label24 = New System.Windows.Forms.Label()
        Me.Label23 = New System.Windows.Forms.Label()
        Me.Label22 = New System.Windows.Forms.Label()
        Me.Label21 = New System.Windows.Forms.Label()
        Me.disc = New System.Windows.Forms.TextBox()
        Me.whtax = New System.Windows.Forms.TextBox()
        Me.dppa = New System.Windows.Forms.TextBox()
        Me.fppa = New System.Windows.Forms.TextBox()
        Me.vat = New System.Windows.Forms.TextBox()
        Me.btn1 = New DevExpress.XtraEditors.SimpleButton()
        Me.GroupBox1.SuspendLayout()
        Me.GroupBox2.SuspendLayout()
        Me.GroupBox17.SuspendLayout()
        Me.SuspendLayout()
        '
        'GroupBox1
        '
        Me.GroupBox1.Controls.Add(Me.cbPrint)
        Me.GroupBox1.Controls.Add(Me.Label44)
        Me.GroupBox1.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox1.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox1.Location = New System.Drawing.Point(8, 275)
        Me.GroupBox1.Margin = New System.Windows.Forms.Padding(4)
        Me.GroupBox1.Name = "GroupBox1"
        Me.GroupBox1.Padding = New System.Windows.Forms.Padding(4)
        Me.GroupBox1.Size = New System.Drawing.Size(269, 68)
        Me.GroupBox1.TabIndex = 26
        Me.GroupBox1.TabStop = False
        Me.GroupBox1.Text = "PRINT SETTING"
        '
        'cbPrint
        '
        Me.cbPrint.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbPrint.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.cbPrint.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbPrint.FormattingEnabled = True
        Me.cbPrint.Items.AddRange(New Object() {"YES", "NO"})
        Me.cbPrint.Location = New System.Drawing.Point(171, 22)
        Me.cbPrint.Margin = New System.Windows.Forms.Padding(4)
        Me.cbPrint.Name = "cbPrint"
        Me.cbPrint.Size = New System.Drawing.Size(84, 36)
        Me.cbPrint.TabIndex = 23
        '
        'Label44
        '
        Me.Label44.AutoSize = True
        Me.Label44.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label44.Location = New System.Drawing.Point(8, 25)
        Me.Label44.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label44.Name = "Label44"
        Me.Label44.Size = New System.Drawing.Size(142, 28)
        Me.Label44.TabIndex = 22
        Me.Label44.Text = "ENABLE PRINT:"
        '
        'GroupBox2
        '
        Me.GroupBox2.Controls.Add(Me.btn1)
        Me.GroupBox2.Controls.Add(Me.GroupBox17)
        Me.GroupBox2.Controls.Add(Me.GroupBox1)
        Me.GroupBox2.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox2.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox2.Location = New System.Drawing.Point(13, 13)
        Me.GroupBox2.Margin = New System.Windows.Forms.Padding(4)
        Me.GroupBox2.Name = "GroupBox2"
        Me.GroupBox2.Padding = New System.Windows.Forms.Padding(4)
        Me.GroupBox2.Size = New System.Drawing.Size(287, 417)
        Me.GroupBox2.TabIndex = 27
        Me.GroupBox2.TabStop = False
        Me.GroupBox2.Text = "SETTINGS"
        '
        'GroupBox17
        '
        Me.GroupBox17.Controls.Add(Me.Label25)
        Me.GroupBox17.Controls.Add(Me.Label24)
        Me.GroupBox17.Controls.Add(Me.Label23)
        Me.GroupBox17.Controls.Add(Me.Label22)
        Me.GroupBox17.Controls.Add(Me.Label21)
        Me.GroupBox17.Controls.Add(Me.disc)
        Me.GroupBox17.Controls.Add(Me.whtax)
        Me.GroupBox17.Controls.Add(Me.dppa)
        Me.GroupBox17.Controls.Add(Me.fppa)
        Me.GroupBox17.Controls.Add(Me.vat)
        Me.GroupBox17.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox17.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox17.Location = New System.Drawing.Point(8, 26)
        Me.GroupBox17.Margin = New System.Windows.Forms.Padding(4)
        Me.GroupBox17.Name = "GroupBox17"
        Me.GroupBox17.Padding = New System.Windows.Forms.Padding(4)
        Me.GroupBox17.Size = New System.Drawing.Size(269, 241)
        Me.GroupBox17.TabIndex = 14
        Me.GroupBox17.TabStop = False
        Me.GroupBox17.Text = "PERCENTAGE SETTINGS"
        '
        'Label25
        '
        Me.Label25.AutoSize = True
        Me.Label25.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label25.Location = New System.Drawing.Point(8, 190)
        Me.Label25.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label25.Name = "Label25"
        Me.Label25.Size = New System.Drawing.Size(193, 28)
        Me.Label25.TabIndex = 17
        Me.Label25.Text = "DEFAULT DISCOUNT"
        '
        'Label24
        '
        Me.Label24.AutoSize = True
        Me.Label24.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label24.Location = New System.Drawing.Point(8, 148)
        Me.Label24.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label24.Name = "Label24"
        Me.Label24.Size = New System.Drawing.Size(165, 28)
        Me.Label24.TabIndex = 16
        Me.Label24.Text = "W/HOLDING TAX"
        '
        'Label23
        '
        Me.Label23.AutoSize = True
        Me.Label23.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label23.Location = New System.Drawing.Point(8, 106)
        Me.Label23.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label23.Name = "Label23"
        Me.Label23.Size = New System.Drawing.Size(147, 28)
        Me.Label23.TabIndex = 15
        Me.Label23.Text = "DOMESTIC PPA"
        '
        'Label22
        '
        Me.Label22.AutoSize = True
        Me.Label22.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label22.Location = New System.Drawing.Point(8, 64)
        Me.Label22.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label22.Name = "Label22"
        Me.Label22.Size = New System.Drawing.Size(133, 28)
        Me.Label22.TabIndex = 14
        Me.Label22.Text = "FOREIGN PPA"
        '
        'Label21
        '
        Me.Label21.AutoSize = True
        Me.Label21.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label21.Location = New System.Drawing.Point(8, 22)
        Me.Label21.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label21.Name = "Label21"
        Me.Label21.Size = New System.Drawing.Size(47, 28)
        Me.Label21.TabIndex = 13
        Me.Label21.Text = "VAT"
        '
        'disc
        '
        Me.disc.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.disc.Location = New System.Drawing.Point(213, 187)
        Me.disc.Margin = New System.Windows.Forms.Padding(4)
        Me.disc.MaxLength = 3
        Me.disc.Name = "disc"
        Me.disc.Size = New System.Drawing.Size(42, 34)
        Me.disc.TabIndex = 12
        '
        'whtax
        '
        Me.whtax.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.whtax.Location = New System.Drawing.Point(213, 145)
        Me.whtax.Margin = New System.Windows.Forms.Padding(4)
        Me.whtax.MaxLength = 3
        Me.whtax.Name = "whtax"
        Me.whtax.Size = New System.Drawing.Size(42, 34)
        Me.whtax.TabIndex = 11
        '
        'dppa
        '
        Me.dppa.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.dppa.Location = New System.Drawing.Point(213, 103)
        Me.dppa.Margin = New System.Windows.Forms.Padding(4)
        Me.dppa.MaxLength = 3
        Me.dppa.Name = "dppa"
        Me.dppa.Size = New System.Drawing.Size(42, 34)
        Me.dppa.TabIndex = 10
        '
        'fppa
        '
        Me.fppa.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.fppa.Location = New System.Drawing.Point(213, 61)
        Me.fppa.Margin = New System.Windows.Forms.Padding(4)
        Me.fppa.MaxLength = 3
        Me.fppa.Name = "fppa"
        Me.fppa.Size = New System.Drawing.Size(42, 34)
        Me.fppa.TabIndex = 9
        '
        'vat
        '
        Me.vat.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.vat.Location = New System.Drawing.Point(213, 19)
        Me.vat.Margin = New System.Windows.Forms.Padding(4)
        Me.vat.MaxLength = 3
        Me.vat.Name = "vat"
        Me.vat.Size = New System.Drawing.Size(42, 34)
        Me.vat.TabIndex = 8
        '
        'btn1
        '
        Me.btn1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn1.Appearance.Options.UseFont = True
        Me.btn1.Location = New System.Drawing.Point(78, 360)
        Me.btn1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn1.Name = "btn1"
        Me.btn1.Size = New System.Drawing.Size(131, 39)
        Me.btn1.TabIndex = 30
        Me.btn1.Text = "Register"
        '
        'frmPercent
        '
        Me.Appearance.BackColor = System.Drawing.Color.White
        Me.Appearance.Options.UseBackColor = True
        Me.Appearance.Options.UseFont = True
        Me.AutoScaleDimensions = New System.Drawing.SizeF(7.0!, 17.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(910, 695)
        Me.Controls.Add(Me.GroupBox2)
        Me.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Name = "frmPercent"
        Me.ShowInTaskbar = False
        Me.Text = "SETTINGS"
        Me.GroupBox1.ResumeLayout(False)
        Me.GroupBox1.PerformLayout()
        Me.GroupBox2.ResumeLayout(False)
        Me.GroupBox17.ResumeLayout(False)
        Me.GroupBox17.PerformLayout()
        Me.ResumeLayout(False)

    End Sub
    Friend WithEvents GroupBox1 As System.Windows.Forms.GroupBox
    Friend WithEvents cbPrint As System.Windows.Forms.ComboBox
    Friend WithEvents Label44 As System.Windows.Forms.Label
    Friend WithEvents GroupBox2 As System.Windows.Forms.GroupBox
    Friend WithEvents GroupBox17 As System.Windows.Forms.GroupBox
    Friend WithEvents Label25 As System.Windows.Forms.Label
    Friend WithEvents Label24 As System.Windows.Forms.Label
    Friend WithEvents Label23 As System.Windows.Forms.Label
    Friend WithEvents Label22 As System.Windows.Forms.Label
    Friend WithEvents Label21 As System.Windows.Forms.Label
    Friend WithEvents disc As System.Windows.Forms.TextBox
    Friend WithEvents whtax As System.Windows.Forms.TextBox
    Friend WithEvents dppa As System.Windows.Forms.TextBox
    Friend WithEvents fppa As System.Windows.Forms.TextBox
    Friend WithEvents vat As System.Windows.Forms.TextBox
    Friend WithEvents btn1 As DevExpress.XtraEditors.SimpleButton
End Class
