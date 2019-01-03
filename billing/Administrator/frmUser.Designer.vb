<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmUser
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
        Me.GroupBox7 = New System.Windows.Forms.GroupBox()
        Me.btn1 = New DevExpress.XtraEditors.SimpleButton()
        Me.LabelControl1 = New DevExpress.XtraEditors.LabelControl()
        Me.txtRegRetypePass = New System.Windows.Forms.TextBox()
        Me.cbRegRole = New System.Windows.Forms.ComboBox()
        Me.txtRegEmpname = New System.Windows.Forms.TextBox()
        Me.txtRegPassword = New System.Windows.Forms.TextBox()
        Me.txtRegUsername = New System.Windows.Forms.TextBox()
        Me.LabelControl2 = New DevExpress.XtraEditors.LabelControl()
        Me.LabelControl3 = New DevExpress.XtraEditors.LabelControl()
        Me.LabelControl4 = New DevExpress.XtraEditors.LabelControl()
        Me.LabelControl5 = New DevExpress.XtraEditors.LabelControl()
        Me.btn2 = New DevExpress.XtraEditors.SimpleButton()
        Me.requser = New DevExpress.XtraEditors.LabelControl()
        Me.reqpass = New DevExpress.XtraEditors.LabelControl()
        Me.reqrepass = New DevExpress.XtraEditors.LabelControl()
        Me.reqempname = New DevExpress.XtraEditors.LabelControl()
        Me.btndelete = New DevExpress.XtraEditors.SimpleButton()
        Me.GroupBox7.SuspendLayout()
        Me.SuspendLayout()
        '
        'GroupBox7
        '
        Me.GroupBox7.Controls.Add(Me.btndelete)
        Me.GroupBox7.Controls.Add(Me.reqempname)
        Me.GroupBox7.Controls.Add(Me.reqrepass)
        Me.GroupBox7.Controls.Add(Me.reqpass)
        Me.GroupBox7.Controls.Add(Me.requser)
        Me.GroupBox7.Controls.Add(Me.btn2)
        Me.GroupBox7.Controls.Add(Me.LabelControl5)
        Me.GroupBox7.Controls.Add(Me.LabelControl4)
        Me.GroupBox7.Controls.Add(Me.LabelControl3)
        Me.GroupBox7.Controls.Add(Me.LabelControl2)
        Me.GroupBox7.Controls.Add(Me.btn1)
        Me.GroupBox7.Controls.Add(Me.LabelControl1)
        Me.GroupBox7.Controls.Add(Me.txtRegRetypePass)
        Me.GroupBox7.Controls.Add(Me.cbRegRole)
        Me.GroupBox7.Controls.Add(Me.txtRegEmpname)
        Me.GroupBox7.Controls.Add(Me.txtRegPassword)
        Me.GroupBox7.Controls.Add(Me.txtRegUsername)
        Me.GroupBox7.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox7.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox7.Location = New System.Drawing.Point(12, 13)
        Me.GroupBox7.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox7.Name = "GroupBox7"
        Me.GroupBox7.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox7.Size = New System.Drawing.Size(570, 518)
        Me.GroupBox7.TabIndex = 12
        Me.GroupBox7.TabStop = False
        Me.GroupBox7.Text = "USER REGISTRATION"
        '
        'btn1
        '
        Me.btn1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn1.Appearance.Options.UseFont = True
        Me.btn1.Location = New System.Drawing.Point(423, 468)
        Me.btn1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn1.Name = "btn1"
        Me.btn1.Size = New System.Drawing.Size(131, 39)
        Me.btn1.TabIndex = 6
        Me.btn1.Text = "Register"
        '
        'LabelControl1
        '
        Me.LabelControl1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.LabelControl1.Location = New System.Drawing.Point(17, 28)
        Me.LabelControl1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.LabelControl1.Name = "LabelControl1"
        Me.LabelControl1.Size = New System.Drawing.Size(107, 28)
        Me.LabelControl1.TabIndex = 15
        Me.LabelControl1.Text = "USERNAME:"
        '
        'txtRegRetypePass
        '
        Me.txtRegRetypePass.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtRegRetypePass.Location = New System.Drawing.Point(17, 235)
        Me.txtRegRetypePass.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.txtRegRetypePass.MaxLength = 50
        Me.txtRegRetypePass.Name = "txtRegRetypePass"
        Me.txtRegRetypePass.PasswordChar = Global.Microsoft.VisualBasic.ChrW(42)
        Me.txtRegRetypePass.Size = New System.Drawing.Size(537, 34)
        Me.txtRegRetypePass.TabIndex = 3
        '
        'cbRegRole
        '
        Me.cbRegRole.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbRegRole.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.cbRegRole.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbRegRole.FormattingEnabled = True
        Me.cbRegRole.Items.AddRange(New Object() {"USER", "ADMIN"})
        Me.cbRegRole.Location = New System.Drawing.Point(17, 403)
        Me.cbRegRole.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.cbRegRole.Name = "cbRegRole"
        Me.cbRegRole.Size = New System.Drawing.Size(537, 36)
        Me.cbRegRole.TabIndex = 5
        '
        'txtRegEmpname
        '
        Me.txtRegEmpname.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtRegEmpname.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtRegEmpname.Location = New System.Drawing.Point(17, 319)
        Me.txtRegEmpname.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.txtRegEmpname.MaxLength = 50
        Me.txtRegEmpname.Name = "txtRegEmpname"
        Me.txtRegEmpname.Size = New System.Drawing.Size(537, 34)
        Me.txtRegEmpname.TabIndex = 4
        '
        'txtRegPassword
        '
        Me.txtRegPassword.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtRegPassword.Location = New System.Drawing.Point(17, 151)
        Me.txtRegPassword.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.txtRegPassword.MaxLength = 50
        Me.txtRegPassword.Name = "txtRegPassword"
        Me.txtRegPassword.PasswordChar = Global.Microsoft.VisualBasic.ChrW(42)
        Me.txtRegPassword.Size = New System.Drawing.Size(537, 34)
        Me.txtRegPassword.TabIndex = 2
        '
        'txtRegUsername
        '
        Me.txtRegUsername.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtRegUsername.Location = New System.Drawing.Point(17, 67)
        Me.txtRegUsername.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.txtRegUsername.MaxLength = 50
        Me.txtRegUsername.Name = "txtRegUsername"
        Me.txtRegUsername.Size = New System.Drawing.Size(537, 34)
        Me.txtRegUsername.TabIndex = 1
        '
        'LabelControl2
        '
        Me.LabelControl2.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.LabelControl2.Location = New System.Drawing.Point(17, 112)
        Me.LabelControl2.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.LabelControl2.Name = "LabelControl2"
        Me.LabelControl2.Size = New System.Drawing.Size(110, 28)
        Me.LabelControl2.TabIndex = 17
        Me.LabelControl2.Text = "PASSWORD:"
        '
        'LabelControl3
        '
        Me.LabelControl3.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.LabelControl3.Location = New System.Drawing.Point(17, 196)
        Me.LabelControl3.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.LabelControl3.Name = "LabelControl3"
        Me.LabelControl3.Size = New System.Drawing.Size(187, 28)
        Me.LabelControl3.TabIndex = 18
        Me.LabelControl3.Text = "RE-TYPE PASSWORD:"
        '
        'LabelControl4
        '
        Me.LabelControl4.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.LabelControl4.Location = New System.Drawing.Point(17, 280)
        Me.LabelControl4.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.LabelControl4.Name = "LabelControl4"
        Me.LabelControl4.Size = New System.Drawing.Size(159, 28)
        Me.LabelControl4.TabIndex = 19
        Me.LabelControl4.Text = "EMPLOYEE NAME:"
        '
        'LabelControl5
        '
        Me.LabelControl5.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.LabelControl5.Location = New System.Drawing.Point(17, 364)
        Me.LabelControl5.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.LabelControl5.Name = "LabelControl5"
        Me.LabelControl5.Size = New System.Drawing.Size(102, 28)
        Me.LabelControl5.TabIndex = 20
        Me.LabelControl5.Text = "USER ROLE:"
        '
        'btn2
        '
        Me.btn2.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn2.Appearance.Options.UseFont = True
        Me.btn2.Location = New System.Drawing.Point(17, 468)
        Me.btn2.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn2.Name = "btn2"
        Me.btn2.Size = New System.Drawing.Size(131, 39)
        Me.btn2.TabIndex = 7
        Me.btn2.Text = "Clear"
        '
        'requser
        '
        Me.requser.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.requser.Appearance.ForeColor = System.Drawing.Color.Red
        Me.requser.Location = New System.Drawing.Point(133, 28)
        Me.requser.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.requser.Name = "requser"
        Me.requser.Size = New System.Drawing.Size(0, 28)
        Me.requser.TabIndex = 22
        '
        'reqpass
        '
        Me.reqpass.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.reqpass.Appearance.ForeColor = System.Drawing.Color.Red
        Me.reqpass.Location = New System.Drawing.Point(133, 112)
        Me.reqpass.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.reqpass.Name = "reqpass"
        Me.reqpass.Size = New System.Drawing.Size(0, 28)
        Me.reqpass.TabIndex = 23
        '
        'reqrepass
        '
        Me.reqrepass.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.reqrepass.Appearance.ForeColor = System.Drawing.Color.Red
        Me.reqrepass.Location = New System.Drawing.Point(210, 196)
        Me.reqrepass.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.reqrepass.Name = "reqrepass"
        Me.reqrepass.Size = New System.Drawing.Size(0, 28)
        Me.reqrepass.TabIndex = 24
        '
        'reqempname
        '
        Me.reqempname.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.reqempname.Appearance.ForeColor = System.Drawing.Color.Red
        Me.reqempname.Location = New System.Drawing.Point(182, 280)
        Me.reqempname.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.reqempname.Name = "reqempname"
        Me.reqempname.Size = New System.Drawing.Size(0, 28)
        Me.reqempname.TabIndex = 25
        '
        'btndelete
        '
        Me.btndelete.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btndelete.Appearance.Options.UseFont = True
        Me.btndelete.Location = New System.Drawing.Point(219, 468)
        Me.btndelete.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btndelete.Name = "btndelete"
        Me.btndelete.Size = New System.Drawing.Size(131, 39)
        Me.btndelete.TabIndex = 26
        Me.btndelete.Text = "Delete User"
        Me.btndelete.Visible = False
        '
        'frmUser
        '
        Me.Appearance.BackColor = System.Drawing.Color.White
        Me.Appearance.Options.UseBackColor = True
        Me.Appearance.Options.UseFont = True
        Me.AutoScaleDimensions = New System.Drawing.SizeF(7.0!, 17.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(1218, 634)
        Me.Controls.Add(Me.GroupBox7)
        Me.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.Name = "frmUser"
        Me.Text = "User Management"
        Me.WindowState = System.Windows.Forms.FormWindowState.Maximized
        Me.GroupBox7.ResumeLayout(False)
        Me.GroupBox7.PerformLayout()
        Me.ResumeLayout(False)

    End Sub
    Friend WithEvents GroupBox7 As System.Windows.Forms.GroupBox
    Friend WithEvents btn1 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents LabelControl1 As DevExpress.XtraEditors.LabelControl
    Friend WithEvents txtRegRetypePass As System.Windows.Forms.TextBox
    Friend WithEvents cbRegRole As System.Windows.Forms.ComboBox
    Friend WithEvents txtRegEmpname As System.Windows.Forms.TextBox
    Friend WithEvents txtRegPassword As System.Windows.Forms.TextBox
    Friend WithEvents txtRegUsername As System.Windows.Forms.TextBox
    Friend WithEvents btn2 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents LabelControl5 As DevExpress.XtraEditors.LabelControl
    Friend WithEvents LabelControl4 As DevExpress.XtraEditors.LabelControl
    Friend WithEvents LabelControl3 As DevExpress.XtraEditors.LabelControl
    Friend WithEvents LabelControl2 As DevExpress.XtraEditors.LabelControl
    Friend WithEvents reqempname As DevExpress.XtraEditors.LabelControl
    Friend WithEvents reqrepass As DevExpress.XtraEditors.LabelControl
    Friend WithEvents reqpass As DevExpress.XtraEditors.LabelControl
    Friend WithEvents requser As DevExpress.XtraEditors.LabelControl
    Friend WithEvents btndelete As DevExpress.XtraEditors.SimpleButton
End Class
