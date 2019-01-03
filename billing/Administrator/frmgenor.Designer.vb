<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmgenor
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
        Dim DataGridViewCellStyle2 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Me.GroupBox7 = New System.Windows.Forms.GroupBox()
        Me.SimpleButton1 = New DevExpress.XtraEditors.SimpleButton()
        Me.LabelControl2 = New DevExpress.XtraEditors.LabelControl()
        Me.btn1 = New DevExpress.XtraEditors.SimpleButton()
        Me.LabelControl1 = New DevExpress.XtraEditors.LabelControl()
        Me.txtto = New System.Windows.Forms.TextBox()
        Me.txtfrom = New System.Windows.Forms.TextBox()
        Me.GroupBox1 = New System.Windows.Forms.GroupBox()
        Me.notice = New DevExpress.XtraEditors.LabelControl()
        Me.lbluser = New DevExpress.XtraEditors.LabelControl()
        Me.btnclear = New DevExpress.XtraEditors.SimpleButton()
        Me.LabelControl5 = New DevExpress.XtraEditors.LabelControl()
        Me.txtsearch = New System.Windows.Forms.TextBox()
        Me.GroupBox2 = New System.Windows.Forms.GroupBox()
        Me.dgor = New System.Windows.Forms.DataGridView()
        Me.Column1 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.GroupBox7.SuspendLayout()
        Me.GroupBox1.SuspendLayout()
        Me.GroupBox2.SuspendLayout()
        CType(Me.dgor, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.SuspendLayout()
        '
        'GroupBox7
        '
        Me.GroupBox7.Controls.Add(Me.SimpleButton1)
        Me.GroupBox7.Controls.Add(Me.LabelControl2)
        Me.GroupBox7.Controls.Add(Me.btn1)
        Me.GroupBox7.Controls.Add(Me.LabelControl1)
        Me.GroupBox7.Controls.Add(Me.txtto)
        Me.GroupBox7.Controls.Add(Me.txtfrom)
        Me.GroupBox7.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox7.Font = New System.Drawing.Font("Segoe UI", 10.2!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox7.Location = New System.Drawing.Point(15, 16)
        Me.GroupBox7.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox7.Name = "GroupBox7"
        Me.GroupBox7.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox7.Size = New System.Drawing.Size(570, 277)
        Me.GroupBox7.TabIndex = 19
        Me.GroupBox7.TabStop = False
        Me.GroupBox7.Text = "GENERATE OR NUMBER"
        '
        'SimpleButton1
        '
        Me.SimpleButton1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.SimpleButton1.Appearance.Options.UseFont = True
        Me.SimpleButton1.Location = New System.Drawing.Point(17, 211)
        Me.SimpleButton1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.SimpleButton1.Name = "SimpleButton1"
        Me.SimpleButton1.Size = New System.Drawing.Size(157, 39)
        Me.SimpleButton1.TabIndex = 23
        Me.SimpleButton1.Text = "Delete OR"
        '
        'LabelControl2
        '
        Me.LabelControl2.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.LabelControl2.Location = New System.Drawing.Point(17, 112)
        Me.LabelControl2.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.LabelControl2.Name = "LabelControl2"
        Me.LabelControl2.Size = New System.Drawing.Size(29, 28)
        Me.LabelControl2.TabIndex = 17
        Me.LabelControl2.Text = "TO:"
        '
        'btn1
        '
        Me.btn1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn1.Appearance.Options.UseFont = True
        Me.btn1.Location = New System.Drawing.Point(423, 211)
        Me.btn1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn1.Name = "btn1"
        Me.btn1.Size = New System.Drawing.Size(131, 39)
        Me.btn1.TabIndex = 6
        Me.btn1.Text = "Generate"
        '
        'LabelControl1
        '
        Me.LabelControl1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.LabelControl1.Location = New System.Drawing.Point(17, 28)
        Me.LabelControl1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.LabelControl1.Name = "LabelControl1"
        Me.LabelControl1.Size = New System.Drawing.Size(59, 28)
        Me.LabelControl1.TabIndex = 15
        Me.LabelControl1.Text = "FROM:"
        '
        'txtto
        '
        Me.txtto.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtto.Location = New System.Drawing.Point(17, 151)
        Me.txtto.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.txtto.MaxLength = 50
        Me.txtto.Name = "txtto"
        Me.txtto.Size = New System.Drawing.Size(537, 34)
        Me.txtto.TabIndex = 2
        '
        'txtfrom
        '
        Me.txtfrom.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtfrom.Location = New System.Drawing.Point(17, 67)
        Me.txtfrom.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.txtfrom.MaxLength = 50
        Me.txtfrom.Name = "txtfrom"
        Me.txtfrom.Size = New System.Drawing.Size(537, 34)
        Me.txtfrom.TabIndex = 1
        '
        'GroupBox1
        '
        Me.GroupBox1.Controls.Add(Me.notice)
        Me.GroupBox1.Controls.Add(Me.lbluser)
        Me.GroupBox1.Controls.Add(Me.btnclear)
        Me.GroupBox1.Controls.Add(Me.LabelControl5)
        Me.GroupBox1.Controls.Add(Me.txtsearch)
        Me.GroupBox1.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox1.Font = New System.Drawing.Font("Segoe UI", 10.2!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox1.Location = New System.Drawing.Point(15, 307)
        Me.GroupBox1.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox1.Name = "GroupBox1"
        Me.GroupBox1.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox1.Size = New System.Drawing.Size(570, 227)
        Me.GroupBox1.TabIndex = 24
        Me.GroupBox1.TabStop = False
        Me.GroupBox1.Text = "CLEAR USER FROM OR NUMBER"
        '
        'notice
        '
        Me.notice.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.notice.Appearance.ForeColor = System.Drawing.Color.Red
        Me.notice.Location = New System.Drawing.Point(223, 28)
        Me.notice.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.notice.Name = "notice"
        Me.notice.Size = New System.Drawing.Size(0, 28)
        Me.notice.TabIndex = 22
        '
        'lbluser
        '
        Me.lbluser.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbluser.Location = New System.Drawing.Point(17, 112)
        Me.lbluser.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.lbluser.Name = "lbluser"
        Me.lbluser.Size = New System.Drawing.Size(80, 28)
        Me.lbluser.TabIndex = 17
        Me.lbluser.Text = "USED BY:"
        '
        'btnclear
        '
        Me.btnclear.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnclear.Appearance.Options.UseFont = True
        Me.btnclear.Location = New System.Drawing.Point(423, 165)
        Me.btnclear.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btnclear.Name = "btnclear"
        Me.btnclear.Size = New System.Drawing.Size(131, 39)
        Me.btnclear.TabIndex = 6
        Me.btnclear.Text = "Clear"
        Me.btnclear.Visible = False
        '
        'LabelControl5
        '
        Me.LabelControl5.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.LabelControl5.Location = New System.Drawing.Point(17, 28)
        Me.LabelControl5.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.LabelControl5.Name = "LabelControl5"
        Me.LabelControl5.Size = New System.Drawing.Size(200, 28)
        Me.LabelControl5.TabIndex = 15
        Me.LabelControl5.Text = "SEARCH BILL NUMBER:"
        '
        'txtsearch
        '
        Me.txtsearch.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtsearch.Location = New System.Drawing.Point(17, 67)
        Me.txtsearch.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.txtsearch.MaxLength = 50
        Me.txtsearch.Name = "txtsearch"
        Me.txtsearch.Size = New System.Drawing.Size(537, 34)
        Me.txtsearch.TabIndex = 1
        '
        'GroupBox2
        '
        Me.GroupBox2.Controls.Add(Me.dgor)
        Me.GroupBox2.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox2.Font = New System.Drawing.Font("Segoe UI", 10.2!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox2.Location = New System.Drawing.Point(597, 16)
        Me.GroupBox2.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox2.Name = "GroupBox2"
        Me.GroupBox2.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox2.Size = New System.Drawing.Size(412, 518)
        Me.GroupBox2.TabIndex = 25
        Me.GroupBox2.TabStop = False
        Me.GroupBox2.Text = "VIEW OR NUMBERS"
        '
        'dgor
        '
        Me.dgor.AllowUserToResizeRows = False
        Me.dgor.BackgroundColor = System.Drawing.Color.White
        Me.dgor.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.dgor.ColumnHeadersHeightSizeMode = System.Windows.Forms.DataGridViewColumnHeadersHeightSizeMode.AutoSize
        Me.dgor.Columns.AddRange(New System.Windows.Forms.DataGridViewColumn() {Me.Column1})
        Me.dgor.Location = New System.Drawing.Point(10, 28)
        Me.dgor.MultiSelect = False
        Me.dgor.Name = "dgor"
        Me.dgor.ReadOnly = True
        Me.dgor.RowHeadersVisible = False
        Me.dgor.RowTemplate.Height = 24
        Me.dgor.SelectionMode = System.Windows.Forms.DataGridViewSelectionMode.FullRowSelect
        Me.dgor.Size = New System.Drawing.Size(393, 480)
        Me.dgor.TabIndex = 0
        '
        'Column1
        '
        Me.Column1.AutoSizeMode = System.Windows.Forms.DataGridViewAutoSizeColumnMode.Fill
        Me.Column1.DataPropertyName = "or_num"
        DataGridViewCellStyle2.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Column1.DefaultCellStyle = DataGridViewCellStyle2
        Me.Column1.HeaderText = "OR NUMBER"
        Me.Column1.Name = "Column1"
        Me.Column1.ReadOnly = True
        Me.Column1.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        '
        'frmgenor
        '
        Me.Appearance.BackColor = System.Drawing.Color.White
        Me.Appearance.Options.UseBackColor = True
        Me.Appearance.Options.UseFont = True
        Me.AutoScaleDimensions = New System.Drawing.SizeF(7.0!, 17.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(1300, 673)
        Me.Controls.Add(Me.GroupBox2)
        Me.Controls.Add(Me.GroupBox1)
        Me.Controls.Add(Me.GroupBox7)
        Me.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Name = "frmgenor"
        Me.Text = "OR #"
        Me.GroupBox7.ResumeLayout(False)
        Me.GroupBox7.PerformLayout()
        Me.GroupBox1.ResumeLayout(False)
        Me.GroupBox1.PerformLayout()
        Me.GroupBox2.ResumeLayout(False)
        CType(Me.dgor, System.ComponentModel.ISupportInitialize).EndInit()
        Me.ResumeLayout(False)

    End Sub
    Friend WithEvents GroupBox7 As System.Windows.Forms.GroupBox
    Friend WithEvents SimpleButton1 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents LabelControl2 As DevExpress.XtraEditors.LabelControl
    Friend WithEvents btn1 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents LabelControl1 As DevExpress.XtraEditors.LabelControl
    Friend WithEvents txtto As System.Windows.Forms.TextBox
    Friend WithEvents txtfrom As System.Windows.Forms.TextBox
    Friend WithEvents GroupBox1 As System.Windows.Forms.GroupBox
    Friend WithEvents notice As DevExpress.XtraEditors.LabelControl
    Friend WithEvents lbluser As DevExpress.XtraEditors.LabelControl
    Friend WithEvents btnclear As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents LabelControl5 As DevExpress.XtraEditors.LabelControl
    Friend WithEvents txtsearch As System.Windows.Forms.TextBox
    Friend WithEvents GroupBox2 As System.Windows.Forms.GroupBox
    Friend WithEvents dgor As System.Windows.Forms.DataGridView
    Friend WithEvents Column1 As System.Windows.Forms.DataGridViewTextBoxColumn
End Class
