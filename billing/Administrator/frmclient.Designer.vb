<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmclient
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
        Me.txtAccountno = New System.Windows.Forms.MaskedTextBox()
        Me.GroupBox3 = New System.Windows.Forms.GroupBox()
        Me.btn2 = New DevExpress.XtraEditors.SimpleButton()
        Me.btn1 = New DevExpress.XtraEditors.SimpleButton()
        Me.txtaccname = New System.Windows.Forms.TextBox()
        Me.Label6 = New System.Windows.Forms.Label()
        Me.Label4 = New System.Windows.Forms.Label()
        Me.GroupBox1.SuspendLayout()
        Me.GroupBox3.SuspendLayout()
        Me.SuspendLayout()
        '
        'GroupBox1
        '
        Me.GroupBox1.Controls.Add(Me.txtAccountno)
        Me.GroupBox1.Controls.Add(Me.GroupBox3)
        Me.GroupBox1.Controls.Add(Me.txtaccname)
        Me.GroupBox1.Controls.Add(Me.Label6)
        Me.GroupBox1.Controls.Add(Me.Label4)
        Me.GroupBox1.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox1.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox1.Location = New System.Drawing.Point(15, 16)
        Me.GroupBox1.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox1.Name = "GroupBox1"
        Me.GroupBox1.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox1.Size = New System.Drawing.Size(688, 224)
        Me.GroupBox1.TabIndex = 31
        Me.GroupBox1.TabStop = False
        Me.GroupBox1.Text = "CLIENT INFO'S"
        '
        'txtAccountno
        '
        Me.txtAccountno.BackColor = System.Drawing.Color.White
        Me.txtAccountno.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtAccountno.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtAccountno.ForeColor = System.Drawing.Color.Black
        Me.txtAccountno.Location = New System.Drawing.Point(145, 25)
        Me.txtAccountno.Margin = New System.Windows.Forms.Padding(4)
        Me.txtAccountno.Mask = "#####-####"
        Me.txtAccountno.Name = "txtAccountno"
        Me.txtAccountno.Size = New System.Drawing.Size(136, 27)
        Me.txtAccountno.TabIndex = 1
        Me.txtAccountno.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'GroupBox3
        '
        Me.GroupBox3.Controls.Add(Me.btn2)
        Me.GroupBox3.Controls.Add(Me.btn1)
        Me.GroupBox3.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox3.Font = New System.Drawing.Font("Segoe UI", 10.2!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox3.Location = New System.Drawing.Point(18, 130)
        Me.GroupBox3.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox3.Name = "GroupBox3"
        Me.GroupBox3.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox3.Size = New System.Drawing.Size(287, 78)
        Me.GroupBox3.TabIndex = 28
        Me.GroupBox3.TabStop = False
        Me.GroupBox3.Text = "FUNCTIONS"
        '
        'btn2
        '
        Me.btn2.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn2.Appearance.Options.UseFont = True
        Me.btn2.Location = New System.Drawing.Point(9, 28)
        Me.btn2.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn2.Name = "btn2"
        Me.btn2.Size = New System.Drawing.Size(131, 39)
        Me.btn2.TabIndex = 4
        Me.btn2.Text = "Delete"
        Me.btn2.Visible = False
        '
        'btn1
        '
        Me.btn1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn1.Appearance.Options.UseFont = True
        Me.btn1.Location = New System.Drawing.Point(146, 28)
        Me.btn1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn1.Name = "btn1"
        Me.btn1.Size = New System.Drawing.Size(131, 39)
        Me.btn1.TabIndex = 3
        Me.btn1.Text = "Register"
        '
        'txtaccname
        '
        Me.txtaccname.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.SuggestAppend
        Me.txtaccname.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.CustomSource
        Me.txtaccname.BackColor = System.Drawing.Color.White
        Me.txtaccname.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.txtaccname.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtaccname.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtaccname.ForeColor = System.Drawing.Color.Black
        Me.txtaccname.Location = New System.Drawing.Point(17, 85)
        Me.txtaccname.Margin = New System.Windows.Forms.Padding(4)
        Me.txtaccname.MaxLength = 50
        Me.txtaccname.Name = "txtaccname"
        Me.txtaccname.Size = New System.Drawing.Size(661, 34)
        Me.txtaccname.TabIndex = 2
        '
        'Label6
        '
        Me.Label6.AutoSize = True
        Me.Label6.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label6.ForeColor = System.Drawing.Color.Black
        Me.Label6.Location = New System.Drawing.Point(13, 53)
        Me.Label6.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label6.Name = "Label6"
        Me.Label6.Size = New System.Drawing.Size(168, 28)
        Me.Label6.TabIndex = 119
        Me.Label6.Text = "ACCOUNT NAME:"
        '
        'Label4
        '
        Me.Label4.AutoSize = True
        Me.Label4.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label4.ForeColor = System.Drawing.Color.Black
        Me.Label4.Location = New System.Drawing.Point(13, 25)
        Me.Label4.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label4.Name = "Label4"
        Me.Label4.Size = New System.Drawing.Size(124, 28)
        Me.Label4.TabIndex = 118
        Me.Label4.Text = "ACCOUNT #:"
        '
        'frmclient
        '
        Me.Appearance.BackColor = System.Drawing.Color.White
        Me.Appearance.Options.UseBackColor = True
        Me.Appearance.Options.UseFont = True
        Me.AutoScaleDimensions = New System.Drawing.SizeF(7.0!, 17.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(743, 520)
        Me.Controls.Add(Me.GroupBox1)
        Me.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Name = "frmclient"
        Me.Text = "CLIENT FORM"
        Me.GroupBox1.ResumeLayout(False)
        Me.GroupBox1.PerformLayout()
        Me.GroupBox3.ResumeLayout(False)
        Me.ResumeLayout(False)

    End Sub
    Friend WithEvents GroupBox1 As System.Windows.Forms.GroupBox
    Friend WithEvents txtAccountno As System.Windows.Forms.MaskedTextBox
    Friend WithEvents GroupBox3 As System.Windows.Forms.GroupBox
    Friend WithEvents txtaccname As System.Windows.Forms.TextBox
    Friend WithEvents Label6 As System.Windows.Forms.Label
    Friend WithEvents Label4 As System.Windows.Forms.Label
    Friend WithEvents btn2 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents btn1 As DevExpress.XtraEditors.SimpleButton
End Class
